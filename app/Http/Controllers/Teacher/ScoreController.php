<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreScoresRequest;
use App\Models\ClassSubject;
use App\Services\Grading\ScoreRecorder;
use Illuminate\Http\JsonResponse;

class ScoreController extends Controller
{
    public function __construct(
        private readonly ScoreRecorder $scoreRecorder,
    ) {}

    /**
     * Batch store or update scores for a course.
     */
    public function store(StoreScoresRequest $request, ClassSubject $classSubject): JsonResponse
    {
        $scores = $request->validated('scores');

        $this->scoreRecorder->record($scores, $request->user());

        return response()->json([
            'message' => 'Nilai berhasil disimpan.',
        ]);
    }
}
