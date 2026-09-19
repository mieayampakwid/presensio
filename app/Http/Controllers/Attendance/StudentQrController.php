<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Attendance\QrCodeRenderer;
use App\Services\Attendance\QrTokenService;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student portal dynamic QR (spec 03 §Requirements 2). The token is bound
 * to the signed-in student's profile and expires in seconds; the page
 * rotates it via Inertia partial reload.
 */
class StudentQrController extends Controller
{
    public function __construct(
        private readonly QrTokenService $tokens,
        private readonly QrCodeRenderer $renderer,
        private readonly SchoolSettings $settings,
    ) {}

    public function show(Request $request): Response
    {
        $student = $request->user()->student;

        return Inertia::render('attendance/my-qr', [
            'qr' => $student === null ? null : $this->qrPayload($student),
        ]);
    }

    /**
     * @return array{token: string, svg: string, expires_at: string, school_timezone: string}
     */
    private function qrPayload(Student $student): array
    {
        $token = $this->tokens->issue($student);

        return [
            'token' => $token->token,
            'svg' => $this->renderer->svg($token->token),
            'expires_at' => $token->expiresAt->toIso8601String(),
            'school_timezone' => $this->settings->timezone(),
        ];
    }
}
