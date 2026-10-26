<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\ScanMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ScannerScanRequest;
use App\Services\Attendance\AttendanceScanService;
use App\Services\Attendance\CredentialResolver;
use App\Services\Attendance\EmployeeScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('scanner/index');
    }

    public function store(ScannerScanRequest $request, CredentialResolver $credentials, AttendanceScanService $students, EmployeeScanService $employees): JsonResponse
    {
        $credential = $request->string('credential')->toString();
        $method = ScanMethod::DynamicQr;
        $resolution = $credentials->resolve($method, $credential);
        $result = $resolution->isEmployee() && $resolution->employee !== null
            ? $employees->scanResolved($method, $resolution->employee, $credential, null)
            : $students->scan($method, $credential, null);

        if ($result->isError()) {
            return response()->json(['outcome' => 'error', 'detail' => $result->outcome->value], 422);
        }

        return response()->json([
            'outcome' => $result->coarse(), 'detail' => $result->outcome->value,
            'student_name' => $result->subjectName(),
            'subject_type' => $result->isEmployee() ? 'employee' : 'student',
            'attendance_status' => $result->attendance?->status->value ?? $result->employeeAttendance?->status->value,
        ]);
    }
}
