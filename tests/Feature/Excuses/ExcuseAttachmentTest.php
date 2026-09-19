<?php

namespace Tests\Feature\Excuses;

use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcuseAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * An excuse with a real file on the fake local disk.
     */
    private function excuseWithFile(array $attributes = []): Excuse
    {
        $excuse = Excuse::factory()->withAttachment()->create($attributes);
        Storage::disk('local')->put((string) $excuse->attachment_path, 'proof-bytes');

        return $excuse;
    }

    public function test_admin_downloads_the_attachment_under_a_friendly_name(): void
    {
        $admin = User::factory()->admin()->create();
        $excuse = $this->excuseWithFile();

        $this->actingAs($admin)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertOk()
            ->assertDownload("excuse-{$excuse->id}.jpg");
    }

    public function test_the_owning_guardian_downloads_the_attachment(): void
    {
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id);

        $excuse = $this->excuseWithFile(['student_id' => $student->id]);

        $this->actingAs($user)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertOk()
            ->assertDownload("excuse-{$excuse->id}.jpg");
    }

    public function test_the_homeroom_teacher_downloads_the_attachment(): void
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->create(['class_id' => $class->id]);

        $excuse = $this->excuseWithFile(['student_id' => $student->id]);

        $this->actingAs($user)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertOk()
            ->assertDownload("excuse-{$excuse->id}.jpg");
    }

    public function test_an_unrelated_guardian_is_forbidden(): void
    {
        $user = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $user->id]);

        $excuse = $this->excuseWithFile();

        $this->actingAs($user)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertForbidden();
    }

    public function test_a_teacher_from_another_class_is_forbidden(): void
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $excuse = $this->excuseWithFile(); // student without a homeroom match

        $this->actingAs($user)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertForbidden();
    }

    public function test_students_are_forbidden(): void
    {
        $user = User::factory()->student()->create();
        $excuse = $this->excuseWithFile();

        $this->actingAs($user)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertForbidden();
    }

    public function test_an_excuse_without_an_attachment_404s(): void
    {
        $admin = User::factory()->admin()->create();
        $excuse = Excuse::factory()->create();

        $this->actingAs($admin)
            ->get(route('excuses.attachment', ['excuse' => $excuse->id]))
            ->assertNotFound();
    }
}
