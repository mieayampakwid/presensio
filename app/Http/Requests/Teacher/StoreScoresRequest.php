<?php

namespace App\Http\Requests\Teacher;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Services\Attendance\ClassAccess;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

class StoreScoresRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        /** @var ClassSubject|null $classSubject */
        $classSubject = $this->route('class_subject');
        if ($classSubject === null) {
            return false;
        }

        return ClassAccess::canWriteCourse($user, $classSubject);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array'],
            'scores.*.assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'scores.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'scores.*.score' => ['nullable', 'numeric'],
            'scores.*.remedial_score' => ['nullable', 'numeric'],
            'scores.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Extra validation: bounds, remedial dependency, roster eligibility, finalization.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ClassSubject $classSubject */
                $classSubject = $this->route('class_subject');
                /** @var array<int, array{assessment_id?: int, student_id?: int, score?: float|int|string|null, remedial_score?: float|int|string|null, notes?: string|null}> $scoresInput */
                $scoresInput = (array) $this->input('scores', []);

                $assessmentIds = array_values(array_unique(array_filter(array_column($scoresInput, 'assessment_id'))));
                /** @var Collection<int, Assessment> $assessments */
                $assessments = Assessment::whereIn('id', $assessmentIds)->with('semester')->get()->keyBy('id');

                $studentIds = array_values(array_unique(array_filter(array_column($scoresInput, 'student_id'))));
                /** @var Collection<int, Enrollment> $enrollments */
                $enrollments = Enrollment::where('class_id', $classSubject->class_id)
                    ->whereIn('student_id', $studentIds)
                    ->get();

                foreach ($scoresInput as $index => $row) {
                    $assessmentId = $row['assessment_id'] ?? null;
                    $studentId = $row['student_id'] ?? null;
                    $score = array_key_exists('score', $row) && $row['score'] !== null ? (float) $row['score'] : null;
                    $remedial = array_key_exists('remedial_score', $row) && $row['remedial_score'] !== null ? (float) $row['remedial_score'] : null;
                    $assessment = $assessments->get($assessmentId);
                    if (! $assessment) {
                        continue;
                    }

                    if ($assessment->class_subject_id !== $classSubject->id) {
                        $validator->errors()->add("scores.{$index}.assessment_id", 'Asesmen bukan bagian dari mata pelajaran ini.');

                        continue;
                    }

                    $courseSemester = CourseSemester::forCourse($classSubject, $assessment->semester);
                    if ($courseSemester->isFinalized()) {
                        $validator->errors()->add("scores.{$index}.assessment_id", 'Mata pelajaran untuk semester ini sudah difinalisasi.');

                        continue;
                    }

                    $maxScore = (float) $assessment->max_score;

                    if ($score !== null) {
                        if ($score < 0 || $score > $maxScore) {
                            $validator->errors()->add("scores.{$index}.score", "Nilai harus berada di antara 0 dan {$maxScore}.");
                        }
                    }

                    if ($remedial !== null) {
                        if ($score === null) {
                            $validator->errors()->add("scores.{$index}.remedial_score", 'Nilai remedial hanya dapat diisi jika nilai utama sudah terisi.');
                        } elseif ($remedial < 0 || $remedial > $maxScore) {
                            $validator->errors()->add("scores.{$index}.remedial_score", "Nilai remedial harus berada di antara 0 dan {$maxScore}.");
                        }
                    }

                    $date = $assessment->assessed_on->toDateString();
                    $isEligible = $enrollments->where('student_id', $studentId)->contains(
                        fn (Enrollment $e) => $e->started_on->toDateString() <= $date
                            && ($e->ended_on === null || $e->ended_on->toDateString() >= $date)
                    );

                    if (! $isEligible) {
                        $validator->errors()->add("scores.{$index}.student_id", "Siswa tidak terdaftar di kelas pada tanggal asesmen ({$date}).");
                    }
                }
            },
        ];
    }
}
