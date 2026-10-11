<?php

namespace App\Services\Grading;

use App\Models\Score;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ScoreRecorder
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record scores in a single database transaction with audit logging per changed score.
     *
     * @param  array<int, array{
     *     assessment_id: int,
     *     student_id: int,
     *     score: float|null,
     *     remedial_score?: float|null,
     *     notes?: string|null
     * }>  $rows
     */
    public function record(array $rows, User $by): void
    {
        DB::transaction(function () use ($rows, $by) {
            foreach ($rows as $row) {
                $assessmentId = (int) $row['assessment_id'];
                $studentId = (int) $row['student_id'];

                $newScore = isset($row['score']) ? (float) $row['score'] : null;
                $newRemedial = isset($row['remedial_score']) ? (float) $row['remedial_score'] : null;
                $newNotes = isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null;

                /** @var Score|null $existing */
                $existing = Score::query()
                    ->where('assessment_id', $assessmentId)
                    ->where('student_id', $studentId)
                    ->first();

                if ($existing === null) {
                    if ($newScore === null && $newRemedial === null && $newNotes === null) {
                        continue;
                    }

                    $score = Score::create([
                        'assessment_id' => $assessmentId,
                        'student_id' => $studentId,
                        'score' => $newScore,
                        'remedial_score' => $newRemedial,
                        'notes' => $newNotes,
                        'graded_by_user_id' => $by->id,
                    ]);

                    $this->auditLogger->record(
                        $score,
                        'score_recorded',
                        null,
                        [
                            'score' => $newScore,
                            'remedial_score' => $newRemedial,
                            'notes' => $newNotes,
                        ]
                    );
                } else {
                    $oldScore = $existing->score !== null ? (float) $existing->score : null;
                    $oldRemedial = $existing->remedial_score !== null ? (float) $existing->remedial_score : null;
                    $oldNotes = $existing->notes;

                    $changed = ($oldScore !== $newScore)
                        || ($oldRemedial !== $newRemedial)
                        || ($oldNotes !== $newNotes);

                    if (! $changed) {
                        continue;
                    }

                    $existing->update([
                        'score' => $newScore,
                        'remedial_score' => $newRemedial,
                        'notes' => $newNotes,
                        'graded_by_user_id' => $by->id,
                    ]);

                    $this->auditLogger->record(
                        $existing,
                        'score_updated',
                        [
                            'score' => $oldScore,
                            'remedial_score' => $oldRemedial,
                            'notes' => $oldNotes,
                        ],
                        [
                            'score' => $newScore,
                            'remedial_score' => $newRemedial,
                            'notes' => $newNotes,
                        ]
                    );
                }
            }
        });
    }
}
