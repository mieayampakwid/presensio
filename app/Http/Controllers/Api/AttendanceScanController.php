<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceScanRequest;
use App\Services\Attendance\AttendanceScanService;
use Illuminate\Http\JsonResponse;

/**
 * Hardware scanner endpoint (spec 03 §Requirements 1). JSON in, JSON out —
 * no sessions, no CSRF; device-key auth + throttle on the route.
 */
class AttendanceScanController extends Controller
{
    public function __invoke(AttendanceScanRequest $request, AttendanceScanService $scanner): JsonResponse
    {
        $result = $scanner->scan(
            $request->scanMethod(),
            $request->string('credential')->toString(),
            $request->deviceScannedAt(),
        );

        if ($result->isError()) {
            return response()->json([
                'outcome' => 'error',
                'detail' => $result->outcome->value,
            ], 422);
        }

        return response()->json([
            'outcome' => $result->coarse(),
            'detail' => $result->outcome->value,
            'student_name' => $result->student?->full_name,
        ]);
    }
}
