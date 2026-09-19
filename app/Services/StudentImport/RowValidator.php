<?php

namespace App\Services\StudentImport;

use App\Models\Student;

/**
 * Validates one raw spreadsheet row against the import policy:
 * full_name and dob required; student_number unique in-file and in DB when
 * present; class must resolve (existing, or auto-create when enabled);
 * guardian rows need name + phone, phone-only links an existing guardian.
 */
class RowValidator
{
    public function __construct(
        private readonly ImportContext $context,
        private readonly ImportOptions $options,
    ) {}

    /**
     * Extract canonical values from a raw row, returning validation errors.
     *
     * @param  list<string>  $row
     * @param  array<string, int>  $indexes  canonical field => column index
     * @return array{data: array<string, string|null>, errors: list<string>}
     */
    public function validate(array $row, array $indexes): array
    {
        $errors = [];

        $cell = fn (string $field): ?string => isset($indexes[$field]) ? mb_substr($row[$indexes[$field]] ?? '', 0, 4096) : null;

        $data = [
            'full_name' => $cell('full_name'),
            'nickname' => $cell('nickname'),
            'dob' => $cell('dob'),
            'student_number' => $cell('student_number'),
            'class' => $cell('class'),
            'guardian_name' => $cell('guardian_name'),
            'guardian_phone' => $cell('guardian_phone'),
            'guardian_work' => $cell('guardian_work'),
            'guardian_address' => $cell('guardian_address'),
        ];

        if (($data['full_name'] ?? '') === '' || $data['full_name'] === null) {
            $errors[] = 'Full name is required.';
        }

        $dob = DateNormalizer::normalize($data['dob'] ?? '');

        if ($dob === null) {
            $errors[] = 'Date of birth is missing or not a recognizable date (dd/mm/yyyy).';
        }

        $data['dob'] = $dob;

        $studentNumber = $data['student_number'];

        if ($studentNumber !== null && $studentNumber !== '') {
            if (isset($this->context->seenStudentNumbers[$studentNumber])) {
                $errors[] = "Student number {$studentNumber} appears twice in this file.";
            }

            if (Student::query()->where('student_number', $studentNumber)->exists()) {
                $errors[] = "Student number {$studentNumber} already exists.";
            }

            $this->context->seenStudentNumbers[$studentNumber] = true;
        } else {
            $data['student_number'] = null;
        }

        $className = $data['class'];

        if ($className !== null && $className !== '') {
            if (! $this->context->isKnownClass($className) && ! $this->options->autoCreateClasses) {
                $errors[] = "Class {$className} does not exist and auto-create is off.";
            }
        } else {
            $data['class'] = null;
        }

        $guardianPhone = $data['guardian_phone'];
        $guardianName = $data['guardian_name'];

        if ($guardianName !== null && $guardianName !== '' && ($guardianPhone === null || $guardianPhone === '')) {
            $errors[] = 'Guardian phone number is required when a guardian name is given.';
        }

        if ($guardianPhone !== null && $guardianPhone !== '' && $guardianName !== null && $guardianName !== '') {
            $this->context->markPendingGuardian($guardianPhone);
        }

        if ($guardianPhone !== null && $guardianPhone !== '' && ($guardianName === null || $guardianName === '')) {
            if ($this->context->guardianIdFor($guardianPhone) === null && ! $this->context->isPendingGuardian($guardianPhone)) {
                $errors[] = "No existing guardian with phone {$guardianPhone} — give the guardian name to create one.";
            }
        }

        if ($guardianPhone === null || $guardianPhone === '') {
            $data['guardian_phone'] = null;
            $data['guardian_name'] = null;
            $data['guardian_work'] = null;
            $data['guardian_address'] = null;
        }

        return ['data' => $data, 'errors' => $errors];
    }
}
