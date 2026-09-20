<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * WAHA (self-hosted WhatsApp HTTP API) text sender (spec 05 §Decisions).
 * Phone numbers become WAHA chatIds: digits only, no leading +, local ID
 * numbers (0…) upgraded to the 62 country code, @c.us appended.
 */
class WhatsAppClient
{
    public function send(string $phone, string $text): void
    {
        $baseUrl = (string) config('services.waha.base_url');

        if ($baseUrl === '') {
            throw new RuntimeException('WAHA_BASE_URL is not configured.');
        }

        Http::baseUrl($baseUrl)
            ->when(
                config('services.waha.api_key'),
                fn ($client, string $apiKey) => $client->withHeaders(['X-Api-Key' => $apiKey]),
            )
            ->connectTimeout(5)
            ->timeout(15)
            // 2 total attempts = one quick in-request retry; queue-level
            // retries (with backoff) come from the job itself. NB:
            // retry($times) counts TOTAL attempts, not retries.
            ->retry(2, 1000, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            })
            ->post('/api/sendText', [
                'session' => (string) config('services.waha.session', 'default'),
                'chatId' => $this->chatId($phone),
                'text' => $text,
            ])
            ->throw();
    }

    /**
     * Guardian phone_number → WAHA chatId. Stored numbers are E.164-ish;
     * tolerate +, spaces, dashes, and local 0-prefixed ID numbers.
     */
    private function chatId(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (Str::startsWith($digits, '0')) {
            $digits = '62'.mb_substr($digits, 1);
        }

        if ($digits === '') {
            throw new RuntimeException("Cannot build a WhatsApp chatId from phone [{$phone}].");
        }

        return $digits.'@c.us';
    }
}
