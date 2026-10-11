<?php

namespace App\Http\Requests\Teacher;

use App\Enums\AssessmentKind;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\LearningObjective;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(AssessmentKind::class)],
            'assessed_on' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'objective_ids' => ['nullable', 'array'],
            'objective_ids.*' => ['integer', 'exists:learning_objectives,id'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    /**
     * Extra validation: semester date range, TP scope, and finalization guard.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ClassSubject $classSubject */
                $classSubject = $this->route('class_subject');
                /** @var Assessment $assessment */
                $assessment = $this->route('assessment');

                $semester = $assessment->semester;
                if (! $semester) {
                    return;
                }

                // Finalization check
                $courseSemester = CourseSemester::forCourse($classSubject, $semester);
                if ($courseSemester->isFinalized()) {
                    $validator->errors()->add('name', 'Mata pelajaran untuk semester ini sudah difinalisasi.');
                }

                // Date range check
                $assessedOn = $this->input('assessed_on');
                if ($assessedOn) {
                    $date = Carbon::parse($assessedOn)->toDateString();
                    $startDate = $semester->starts_at->toDateString();
                    $endDate = $semester->ends_at->toDateString();

                    if ($date < $startDate || $date > $endDate) {
                        $validator->errors()->add('assessed_on', 'Tanggal asesmen harus berada dalam rentang semester.');
                    }
                }

                $kind = $this->input('kind');
                $objectiveIds = $this->input('objective_ids', []);

                // AC-10-05: summative_scope requires min 1 TP
                if (($kind === AssessmentKind::SummativeScope->value || $kind === AssessmentKind::SummativeScope) && empty($objectiveIds)) {
                    $validator->errors()->add('objective_ids', 'Asesmen sumatif lingkup materi harus memiliki minimal 1 tujuan pembelajaran.');
                }

                // Verify each TP matches course scope (year, subject, grade_level)
                if (! empty($objectiveIds)) {
                    $classSubject->loadMissing('schoolClass');
                    $expectedYearId = $classSubject->schoolClass->academic_year_id;
                    $expectedSubjectId = $classSubject->subject_id;
                    $expectedGradeLevel = $classSubject->schoolClass->grade_level;

                    $validCount = LearningObjective::query()
                        ->whereIn('id', $objectiveIds)
                        ->where('academic_year_id', $expectedYearId)
                        ->where('subject_id', $expectedSubjectId)
                        ->where('grade_level', $expectedGradeLevel)
                        ->count();

                    if ($validCount !== count($objectiveIds)) {
                        $validator->errors()->add('objective_ids', 'Tujuan pembelajaran tidak sesuai dengan mata pelajaran dan tingkat kelas kursus ini.');
                    }
                }
            },
        ];
    }
}
