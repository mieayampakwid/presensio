<?php

namespace Tests\Feature\StudentImport;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentImportMapping;
use App\Services\StudentImport\CsvRowReader;
use App\Services\StudentImport\ImportOptions;
use App\Services\StudentImport\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StudentImportService::class);
    }

    public function test_preview_writes_nothing(): void
    {
        $reader = $this->csv("Nama;Tanggal Lahir;NIS;Kelas\nAyu Lestari;17/05/2012;007;1A\n");

        $result = $this->service->preview(
            $reader,
            ['full_name' => 'Nama', 'dob' => 'Tanggal Lahir', 'student_number' => 'NIS', 'class' => 'Kelas'],
            new ImportOptions(autoCreateClasses: true),
        );

        $this->assertSame(1, $result->validCount);
        $this->assertSame([], $result->errors);
        $this->assertSame(0, Student::count());
        $this->assertSame(0, SchoolClass::count());
    }

    public function test_partial_import_commits_valid_rows_and_reports_bad_ones(): void
    {
        $reader = $this->csv("Nama;Tanggal Lahir\nAyu Lestari;17/05/2012\nTanpa Tanggal;\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(1, $result->validCount);
        $this->assertCount(1, $result->errors);
        $this->assertSame(3, $result->errors[0]['row']);

        $this->assertSame(1, Student::count());
        $this->assertSame('Ayu Lestari', Student::first()->full_name);
        $this->assertSame('2012-05-17', Student::first()->dob->toDateString());
    }

    public function test_duplicate_student_numbers_within_the_file_are_rejected(): void
    {
        $reader = $this->csv("Nama;NIS;Tanggal Lahir\nAyu;007;17/05/2012\nBudi;007;18/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'student_number' => 'NIS', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(1, $result->validCount);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('twice in this file', $result->errors[0]['errors'][0]);
        $this->assertSame('Ayu', Student::first()->full_name);
    }

    public function test_student_numbers_already_in_the_database_are_rejected(): void
    {
        Student::factory()->create(['student_number' => '007']);
        $reader = $this->csv("Nama;NIS;Tanggal Lahir\nBudi;007;18/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'student_number' => 'NIS', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(0, $result->validCount);
        $this->assertStringContainsString('already exists', $result->errors[0]['errors'][0]);
        $this->assertSame(1, Student::count());
    }

    public function test_missing_class_fails_when_auto_create_is_off(): void
    {
        $reader = $this->csv("Nama;Kelas;Tanggal Lahir\nAyu;1Z;17/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'class' => 'Kelas', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(0, $result->validCount);
        $this->assertStringContainsString('auto-create is off', $result->errors[0]['errors'][0]);
    }

    public function test_missing_class_is_auto_created_when_enabled(): void
    {
        $reader = $this->csv("Nama;Kelas;Tanggal Lahir\nAyu;1Z;17/05/2012\nBudi;1Z;18/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'class' => 'Kelas', 'dob' => 'Tanggal Lahir'],
            new ImportOptions(autoCreateClasses: true),
        );

        $this->assertSame(2, $result->validCount);
        $this->assertSame(1, SchoolClass::where('name', '1Z')->count());
        $this->assertSame(
            2,
            Student::whereHas('enrollments.schoolClass', fn ($query) => $query->where('classes.name', '1Z'))->count(),
        );
    }

    public function test_sibling_rows_share_one_guardian(): void
    {
        $reader = $this->csv("Nama;Nama Wali;No HP Wali;Tanggal Lahir\nAyu;Slamet Riyadi;+6281111111111;17/05/2012\nBudi;Slamet Riyadi;+6281111111111;18/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'guardian_name' => 'Nama Wali', 'guardian_phone' => 'No HP Wali', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(2, $result->validCount);
        $this->assertSame(1, Guardian::count());

        $guardian = Guardian::first();
        $this->assertSame(2, $guardian->students()->count());
    }

    public function test_phone_only_rows_link_an_existing_guardian(): void
    {
        $guardian = Guardian::factory()->create(['phone_number' => '+6281111111111']);
        $reader = $this->csv("Nama;No HP Wali;Tanggal Lahir\nAyu;+6281111111111;17/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'guardian_phone' => 'No HP Wali', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(1, $result->validCount);
        $this->assertSame(1, Guardian::count());
        $this->assertTrue($guardian->students()->where('full_name', 'Ayu')->exists());
    }

    public function test_phone_only_rows_without_a_matching_guardian_are_rejected(): void
    {
        $reader = $this->csv("Nama;No HP Wali;Tanggal Lahir\nAyu;+6281111111111;17/05/2012\n");

        $result = $this->service->run(
            $reader,
            $reader->fingerprint(),
            ['full_name' => 'Nama', 'guardian_phone' => 'No HP Wali', 'dob' => 'Tanggal Lahir'],
            new ImportOptions,
        );

        $this->assertSame(0, $result->validCount);
        $this->assertStringContainsString('No existing guardian', $result->errors[0]['errors'][0]);
    }

    public function test_run_persists_the_mapping_for_the_header_fingerprint(): void
    {
        $reader = $this->csv("Nama;Tanggal Lahir\nAyu;17/05/2012\n");
        $mapping = ['full_name' => 'Nama', 'dob' => 'Tanggal Lahir'];

        $this->service->run($reader, $reader->fingerprint(), $mapping, new ImportOptions(autoCreateClasses: true));

        $stored = StudentImportMapping::query()->where('header_fingerprint', $reader->fingerprint())->first();

        $this->assertNotNull($stored);
        $this->assertSame($mapping, $stored->mapping['columns']);
        $this->assertTrue($stored->mapping['options']['auto_create_classes']);
    }

    private function csv(string $content): CsvRowReader
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return new CsvRowReader($stream);
    }
}
