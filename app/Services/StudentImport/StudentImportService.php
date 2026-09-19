<?php

namespace App\Services\StudentImport;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentImportMapping;
use Illuminate\Support\Facades\DB;

/**
 * Two-phase student importer: preview() validates without writing; run()
 * commits all valid rows inside one transaction (partial import — bad rows
 * are reported, good rows stick).
 */
class StudentImportService
{
    private const PREVIEW_ROW_LIMIT = 25;

    /**
     * Dry-run: reports which rows would import, writing nothing.
     *
     * @param  array<string, string>  $mapping  canonical field => raw header
     */
    public function preview(RowReader $reader, array $mapping, ImportOptions $options): ImportResult
    {
        return $this->process($reader, $mapping, $options, commit: false);
    }

    /**
     * Commits all valid rows inside one transaction, then remembers the
     * column mapping for future uploads with the same headers.
     *
     * @param  array<string, string>  $mapping  canonical field => raw header
     */
    public function run(RowReader $reader, string $fingerprint, array $mapping, ImportOptions $options): ImportResult
    {
        $result = DB::transaction(fn (): ImportResult => $this->process($reader, $mapping, $options, commit: true));

        StudentImportMapping::query()->updateOrCreate(
            ['header_fingerprint' => $fingerprint],
            ['mapping' => ['columns' => $mapping, 'options' => $options->toArray()]],
        );

        return $result;
    }

    /**
     * @param  array<string, string>  $mapping  canonical field => raw header
     * @return array<string, int> canonical field => column index
     */
    public function columnIndexes(RowReader $reader, array $mapping): array
    {
        $indexes = [];

        foreach ($mapping as $field => $header) {
            $index = array_search($header, $reader->headers(), true);

            if ($index !== false) {
                $indexes[$field] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @param  array<string, string>  $mapping  canonical field => raw header
     */
    private function process(RowReader $reader, array $mapping, ImportOptions $options, bool $commit): ImportResult
    {
        $indexes = $this->columnIndexes($reader, $mapping);
        $context = new ImportContext;
        $validator = new RowValidator($context, $options);

        $valid = [];
        $errors = [];
        $rowNumber = 1; // header row is row 1; data starts at 2.

        foreach ($reader->rows() as $row) {
            $rowNumber++;

            $checked = $validator->validate($row, $indexes);

            if ($checked['errors'] !== []) {
                $errors[] = ['row' => $rowNumber, 'errors' => $checked['errors']];

                continue;
            }

            $valid[] = $checked['data'];
        }

        if ($commit) {
            foreach ($valid as $data) {
                $this->commitRow($data, $context);
            }
        }

        return new ImportResult(
            validCount: count($valid),
            errors: $errors,
            rows: array_slice($valid, 0, self::PREVIEW_ROW_LIMIT),
        );
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function commitRow(array $data, ImportContext $context): void
    {
        $classId = null;

        if ($data['class'] !== null) {
            $className = $data['class'];

            if (! $context->isKnownClass($className)) {
                $class = SchoolClass::query()->create(['name' => $className]);
                $context->stageClass($className, $class->id);
            }

            $classId = $context->classes[$className];
        }

        $guardian = null;

        if ($data['guardian_phone'] !== null) {
            $phone = $data['guardian_phone'];
            $guardianId = $context->guardianIdFor($phone);

            if ($guardianId === null) {
                $guardian = Guardian::query()->create([
                    'name' => $data['guardian_name'],
                    'phone_number' => $phone,
                    'work' => $data['guardian_work'],
                    'address' => $data['guardian_address'],
                ]);
                $context->stageGuardian($phone, $guardian->id);
            } else {
                $guardian = Guardian::query()->findOrFail($guardianId);
            }
        }

        $student = Student::query()->create([
            'full_name' => $data['full_name'],
            'nickname' => $data['nickname'],
            'dob' => $data['dob'],
            'student_number' => $data['student_number'],
            'class_id' => $classId,
        ]);

        if ($guardian !== null) {
            $student->guardians()->syncWithoutDetaching([$guardian->id]);
        }
    }
}
