<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Attendance\QrCodeRenderer;
use App\Services\Attendance\QrTokenService;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeQrController extends Controller
{
    public function __construct(
        private readonly QrTokenService $tokens,
        private readonly QrCodeRenderer $renderer,
        private readonly SchoolSettings $settings,
    ) {}

    public function show(Request $request): Response
    {
        $employee = $request->user()->employee;

        return Inertia::render('attendance/my-qr', [
            'qr' => $employee === null ? null : $this->qrPayload($employee),
            'subject_type' => 'employee',
        ]);
    }

    /**
     * @return array{token: string, svg: string, expires_at: string, school_timezone: string}
     */
    public function qrPayload(Employee $employee): array
    {
        $token = $this->tokens->issueForEmployee($employee);

        return [
            'token' => $token->token,
            'svg' => $this->renderer->svg($token->token),
            'expires_at' => $token->expiresAt->toIso8601String(),
            'school_timezone' => $this->settings->timezone(),
        ];
    }
}
