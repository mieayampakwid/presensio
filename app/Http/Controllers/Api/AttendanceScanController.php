<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceScanRequest;
use App\Services\Attendance\AttendanceScanService;
use App\Services\Attendance\CredentialResolver;
use App\Services\Attendance\EmployeeScanService;
use Illuminate\Http\JsonResponse;

/**
 * Hardware scanner endpoint (spec 03 §Requirements 1, spec 16 §Req 2). JSON in, JSON out —
 * no sessions, no CSRF; device-key auth + throttle on the route.
 */
class AttendanceScanController extends Controller
{
    public function __invoke(
        AttendanceScanRequest $request,
        AttendanceScanService $studentScanner,
        EmployeeScanService $employeeScanner,
        CredentialResolver $credentials,
    ): JsonResponse {
        $method = $request->scanMethod();
        $credential = $request->string('credential')->toString();
        $scannedAt = $request->deviceScannedAt();

        $resolution = $credentials->resolve($method, $credential);

        if ($resolution->isEmployee() && $resolution->employee !== null) {
            $result = $employeeScanner->scanResolved(
                $method,
                $resolution->employee,
                $credential,
                $scannedAt,
            );
        } else {
            $result = $studentScanner->scan(
                $method,
                $credential,
                $scannedAt,
            );
        }

        if ($result->isError()) {
            return response()->json([
                'outcome' => 'error',
                'detail' => $result->outcome->value,
            ], 422);
        }

        return response()->json([
            'outcome' => $result->coarse(),
            'detail' => $result->outcome->value,
            'student_name' => $result->subjectName(),
            'subject_type' => $result->isEmployee() ? 'employee' : 'student',
        ]);
    }
}
