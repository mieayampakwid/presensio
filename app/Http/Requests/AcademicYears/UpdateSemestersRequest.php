<?php

namespace App\Http\Requests\AcademicYears;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSemestersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'semesters' => ['required', 'array', 'size:2'],
            'semesters.*.number' => ['required', 'integer', 'in:1,2'],
            'semesters.*.starts_at' => ['required', 'date_format:Y-m-d'],
            'semesters.*.ends_at' => ['required', 'date_format:Y-m-d', 'after:semesters.*.starts_at'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $academicYear = $this->route('academic_year');
            if (! $academicYear instanceof AcademicYear) {
                return;
            }

            $rawSemesters = $this->input('semesters', []);
            if (! is_array($rawSemesters) || count($rawSemesters) !== 2) {
                return;
            }

            $semesters = [];
            foreach ($rawSemesters as $s) {
                if (isset($s['number'])) {
                    $semesters[$s['number']] = $s;
                }
            }

            if (! isset($semesters[1], $semesters[2])) {
                $v->errors()->add('semesters', 'Both Semester Ganjil and Semester Genap are required.');

                return;
            }

            $ganjilStart = Carbon::parse($semesters[1]['starts_at'] ?? null);
            $ganjilEnd = Carbon::parse($semesters[1]['ends_at'] ?? null);
            $genapStart = Carbon::parse($semesters[2]['starts_at'] ?? null);
            $genapEnd = Carbon::parse($semesters[2]['ends_at'] ?? null);

            $yearStart = Carbon::parse($academicYear->starts_at);
            $yearEnd = Carbon::parse($academicYear->ends_at);

            // Ganjil starts_at = year start
            if (! $ganjilStart->equalTo($yearStart)) {
                $v->errors()->add('semesters.0.starts_at', 'Semester Ganjil must start on the academic year start date ('.$yearStart->toDateString().').');
            }

            // Genap ends_at = year end
            if (! $genapEnd->equalTo($yearEnd)) {
                $v->errors()->add('semesters.1.ends_at', 'Semester Genap must end on the academic year end date ('.$yearEnd->toDateString().').');
            }

            // Ganjil ends_at < Genap starts_at
            if (! $ganjilEnd->lessThan($genapStart)) {
                $v->errors()->add('semesters.1.starts_at', 'Semester Genap must start after Semester Ganjil ends.');
            }

            // Contiguous: Genap start = Ganjil end + 1 day
            if (! $genapStart->equalTo($ganjilEnd->copy()->addDay())) {
                $v->errors()->add('semesters.1.starts_at', 'Semester Genap must start the day immediately following Semester Ganjil.');
            }
        });
    }
}
