<?php

namespace Tests\Unit\Services\Notifications;

use App\Services\Notifications\WhatsAppClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatsAppClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.waha.base_url' => 'http://waha.test']);
    }

    public function test_sends_the_expected_body_and_normalizes_e164_numbers(): void
    {
        Http::fake();

        app(WhatsAppClient::class)->send('+62 811-1111-111', 'pesan uji');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://waha.test/api/sendText'
                && $request['session'] === 'default'
                && $request['chatId'] === '628111111111@c.us'
                && $request['text'] === 'pesan uji';
        });
    }

    public function test_local_zero_prefixed_numbers_are_upgraded_to_the_country_code(): void
    {
        Http::fake();

        app(WhatsAppClient::class)->send('081234567890', 'pesan uji');

        Http::assertSent(fn (Request $request) => $request['chatId'] === '6281234567890@c.us');
    }

    public function test_sends_the_api_key_header_when_configured(): void
    {
        config(['services.waha.api_key' => 'secret-key']);
        Http::fake();

        app(WhatsAppClient::class)->send('+628111111111', 'pesan uji');

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Api-Key', 'secret-key'));
    }

    public function test_server_errors_are_retried_once_then_succeed(): void
    {
        Http::fakeSequence('/api/sendText')
            ->push(['success' => false], 500)
            ->push(['success' => true]);

        app(WhatsAppClient::class)->send('+628111111111', 'pesan uji');

        Http::assertSentCount(2);
    }

    public function test_exhausted_server_errors_throw_after_both_attempts(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        try {
            app(WhatsAppClient::class)->send('+628111111111', 'pesan uji');
            $this->fail('Expected a RequestException after the retry exhausted.');
        } catch (RequestException $exception) {
            $this->assertSame(500, $exception->response->status());
            Http::assertSentCount(2);
        }
    }

    public function test_connection_errors_are_retried(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused');
        });

        // retry(1) exhausts its single retry inside send() and rethrows.
        $this->expectException(ConnectionException::class);

        app(WhatsAppClient::class)->send('+628111111111', 'pesan uji');
    }

    public function test_client_errors_throw_without_retrying(): void
    {
        Http::fake(['*' => Http::response(['message' => 'bad request'], 400)]);

        try {
            app(WhatsAppClient::class)->send('+628111111111', 'pesan uji');
            $this->fail('Expected a RequestException for the 400 response.');
        } catch (RequestException $exception) {
            $this->assertSame(400, $exception->response->status());
            Http::assertSentCount(1);
        }
    }

    public function test_a_blank_base_url_throws_before_any_request(): void
    {
        config(['services.waha.base_url' => null]);
        Http::fake();

        $this->expectException(RuntimeException::class);

        app(WhatsAppClient::class)->send('+628111111111', 'pesan uji');
    }
}
