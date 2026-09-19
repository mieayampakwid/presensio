<?php

namespace Tests\Feature\StudentImport;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentImportHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_fresh_headers_show_the_mapping_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->createWithContent('students.csv', "Nama;Kelas\nAyu;1A\n");

        $response = $this->actingAs($admin)
            ->post(route('students.import.store'), ['file' => $file]);

        $response->assertOk();
        $response->assertSee('students/import/map');
        $response->assertSee('Nama');
    }

    public function test_identical_reupload_skips_straight_to_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $content = "Nama;Kelas\nAyu;1A\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $content);

        $this->actingAs($admin)->post(route('students.import.store'), [
            'file' => $file,
            'auto_create_classes' => '1',
        ]);

        // Complete the first flow (run remembers the mapping).
        $token = $this->extractToken('csv');
        $this->actingAs($admin)->post(route('students.import.run'), [
            'token' => $token,
            'mapping' => ['full_name' => 'Nama', 'class' => 'Kelas'],
            'auto_create_classes' => '1',
        ]);

        $file2 = UploadedFile::fake()->createWithContent('again.csv', $content);

        $this->actingAs($admin)
            ->post(route('students.import.store'), ['file' => $file2])
            ->assertOk()
            ->assertSee('students/import/preview');
    }

    public function test_unmapped_full_name_is_rejected_on_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $content = "Nama;Kelas\nAyu;1A\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $content);

        $this->actingAs($admin)->post(route('students.import.store'), ['file' => $file]);

        $token = $this->extractToken('csv');

        $this->actingAs($admin)
            ->post(route('students.import.preview'), [
                'token' => $token,
                'mapping' => ['class' => 'Kelas'],
            ])
            ->assertSessionHasErrors('mapping.full_name');
    }

    public function test_run_commits_rows_and_deletes_the_temp_file(): void
    {
        $admin = User::factory()->admin()->create();
        $content = "Nama;Kelas;Tanggal Lahir\nAyu;1A;17/05/2012\nBroken;;\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $content);

        $this->actingAs($admin)->post(route('students.import.store'), ['file' => $file]);

        $token = $this->extractToken('csv');

        $this->actingAs($admin)->post(route('students.import.run'), [
            'token' => $token,
            'mapping' => ['full_name' => 'Nama', 'class' => 'Kelas', 'dob' => 'Tanggal Lahir'],
            'auto_create_classes' => '1',
        ])->assertRedirect(route('students.index'));

        $this->assertSame(1, Student::count());
        $this->assertSame('Ayu', Student::first()->full_name);
        $this->assertSame(0, collect(Storage::disk('local')->files('student-imports'))->count());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_importing(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('students.import.create'))
            ->assertForbidden();
    }

    public function test_legacy_xls_files_are_rejected_with_guidance(): void
    {
        $admin = User::factory()->admin()->create();
        // Real binary .xls content (OLE compound document magic).
        $file = UploadedFile::fake()->createWithContent(
            'students.xls',
            "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1binary",
        );

        $this->actingAs($admin)
            ->post(route('students.import.store'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_xlsx_files_import_end_to_end(): void
    {
        $admin = User::factory()->admin()->create();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Nama', 'Kelas', 'Tanggal Lahir']));
        $writer->addRow(Row::fromValues(['Ayu', '1A', '17/05/2012']));
        $writer->close();

        $file = UploadedFile::fake()->createWithContent(
            'students.xlsx',
            (string) file_get_contents($path),
        );

        $this->actingAs($admin)->post(route('students.import.store'), ['file' => $file]);

        $token = $this->extractToken('xlsx');

        $this->actingAs($admin)->post(route('students.import.run'), [
            'token' => $token,
            'mapping' => ['full_name' => 'Nama', 'class' => 'Kelas', 'dob' => 'Tanggal Lahir'],
            'auto_create_classes' => '1',
        ])->assertRedirect(route('students.index'));

        $this->assertSame(1, Student::count());
        $this->assertSame('Ayu', Student::first()->full_name);
    }

    public function test_expired_or_unknown_token_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('students.import.preview'), [
                'token' => str_repeat('a', 40),
                'mapping' => ['full_name' => 'Nama', 'class' => 'Kelas'],
            ])
            ->assertStatus(419);
    }

    private function extractToken(string $extension): string
    {
        $files = Storage::disk('local')->files('student-imports');

        foreach ($files as $file) {
            if (str_ends_with($file, '.'.$extension)) {
                return basename($file, '.'.$extension);
            }
        }

        $this->fail("No .{$extension} import temp file was stored.");
    }

    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'student' => ['student'],
            'parent' => ['parent'],
        ];
    }
}
