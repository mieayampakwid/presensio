<?php

namespace Tests\Feature\Announcements;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AnnouncementReceipt;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-24 03:00:00', 'UTC'));
    }

    /** @return array<string,array{UserRole}> */
    public static function roles(): array
    {
        $rows = [];
        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::ScannerOperator) {
                continue;
            }
            $rows[$role->value] = [$role];
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace(['title' => 'Pengumuman uji', 'body' => 'Isi **resmi**', 'audience_type' => 'all', 'is_pinned' => false, 'requires_acknowledgement' => false, 'notify_urgent' => false, 'published_at' => '2026-10-24T10:00:00'], $overrides);
    }

    /** @return array<string,mixed> */
    private function classroom(): array
    {
        $year = AcademicYear::factory()->current()->create();
        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->forUser($teacherUser)->create();
        $class = SchoolClass::factory()->create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'grade_level' => 5]);
        $studentUser = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        Enrollment::create(['student_id' => $student->id, 'class_id' => $class->id, 'started_on' => '2026-07-01']);
        $parent = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
        $guardian->students()->attach($student, ['relationship_type' => 'father']);

        return compact('year', 'teacherUser', 'teacher', 'class', 'studentUser', 'student', 'parent', 'guardian');
    }

    #[DataProvider('roles')]
    public function test_every_role_can_read_own_role_notice_and_acknowledge_but_authority_is_scoped(UserRole $role): void
    {
        $user = User::factory()->withRoles($role)->create();
        $item = Announcement::factory()->create(['audience_type' => 'role', 'audience_role' => $role->value, 'requires_acknowledgement' => true]);
        $this->actingAs($user)->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->component('announcements/index')->has('announcements.data', 1));
        $this->assertDatabaseCount('announcement_receipts', 0);
        $this->get(route('announcements.show', $item))->assertOk();
        $first = AnnouncementReceipt::sole()->read_at;
        $this->travel(1)->minutes();
        $this->post(route('announcements.acknowledge', $item))->assertRedirect();
        $ack = AnnouncementReceipt::sole()->acknowledged_at;
        $this->post(route('announcements.acknowledge', $item))->assertRedirect();
        $this->assertTrue($first->equalTo(AnnouncementReceipt::sole()->read_at));
        $this->assertTrue($ack->equalTo(AnnouncementReceipt::sole()->acknowledged_at));
        $this->assertDatabaseCount('announcement_receipts', 1);
        if (in_array($role, [UserRole::Admin, UserRole::Principal], true)) {
            $this->post(route('announcements.store'), $this->payload())->assertRedirect();
            $this->assertDatabaseCount('announcements', 2);
        } else {
            $this->post(route('announcements.store'), $this->payload())->assertForbidden();
            $this->get(route('announcements.recipients', $item))->assertForbidden();
            $this->delete(route('announcements.destroy', $item))->assertForbidden();
            $this->assertDatabaseCount('announcements', 1);
        }
    }

    public function test_parent_and_student_visibility_follow_current_enrollment_and_not_counselor_inspection_scope(): void
    {
        $f = $this->classroom();
        $other = SchoolClass::factory()->create(['academic_year_id' => $f['year']->id, 'grade_level' => 6]);
        $item = Announcement::factory()->create(['audience_type' => 'class', 'class_id' => $f['class']->id, 'academic_year_id' => $f['year']->id]);
        $hidden = Announcement::factory()->create(['audience_type' => 'class', 'class_id' => $other->id, 'academic_year_id' => $f['year']->id]);
        $this->actingAs($f['parent'])->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->where('announcements.data.0.id', $item->id)->has('announcements.data', 1));
        $this->get(route('announcements.show', $hidden))->assertForbidden();
        $this->get(route('announcements.attachment', $hidden))->assertForbidden();
        $this->post(route('announcements.acknowledge', $hidden))->assertForbidden();
        $this->assertDatabaseCount('announcement_receipts', 0);
        $f['student']->enrollments()->update(['ended_on' => '2026-10-23']);
        Enrollment::create(['student_id' => $f['student']->id, 'class_id' => $other->id, 'started_on' => '2026-10-24']);
        $this->actingAs($f['studentUser'])->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->has('announcements.data', 1)->where('announcements.data.0.id', $hidden->id));
        $counselor = User::factory()->withRoles(UserRole::Teacher, UserRole::Counselor)->create();
        Teacher::factory()->forUser($counselor)->create();
        $this->actingAs($counselor)->post(route('classes.announcements.store', $other), $this->payload(['audience_type' => 'class', 'class_id' => $other->id]))->assertForbidden();
        $this->assertDatabaseCount('announcements', 2);
    }

    public function test_subject_teacher_can_author_only_assigned_class_and_loses_mutation_report_access_after_removal(): void
    {
        $f = $this->classroom();
        $f['class']->update(['teacher_id' => null]);
        $assignment = ClassSubject::factory()->create(['class_id' => $f['class']->id, 'teacher_id' => $f['teacher']->id]);
        $this->actingAs($f['teacherUser'])->post(route('classes.announcements.store', $f['class']), $this->payload(['audience_type' => 'class', 'class_id' => $f['class']->id]))->assertRedirect();
        $item = Announcement::sole();
        $this->get(route('announcements.recipients', $item))->assertOk();
        $this->get(route('classes.index'))->assertInertia(fn (Assert $page) => $page->where('classes.data.0.can_announce', true));
        $assignment->delete();
        $this->get(route('announcements.show', $item))->assertOk();
        $this->get(route('announcements.edit', $item))->assertForbidden();
        $this->get(route('announcements.recipients', $item))->assertForbidden();
        $this->delete(route('announcements.destroy', $item))->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_drafts_future_pins_search_and_dashboard_badge_are_consistent_without_read_writes(): void
    {
        $reader = User::factory()->staff()->create();
        Announcement::factory()->draft()->create();
        Announcement::factory()->scheduled()->create();
        $pinned = Announcement::factory()->pinned()->create(['title' => 'Istimewa lama', 'published_at' => now()->subDays(20)]);
        Announcement::factory()->count(12)->create(['requires_acknowledgement' => true]);
        $this->actingAs($reader)->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->has('announcements.data', 10)->where('announcements.data.0.id', $pinned->id)->where('announcements.total', 13));
        $this->get(route('announcements.index', ['q' => 'istimewa']))->assertInertia(fn (Assert $page) => $page->has('announcements.data', 1));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('announcementSummary.items', 3)->where('announcementSummary.unacknowledged_count', 12));
        $this->assertDatabaseCount('announcement_receipts', 0);
    }

    public function test_acknowledgement_rejects_nonrequired_draft_and_future_items_and_direct_ack_records_first_read(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $this->post(route('announcements.acknowledge', Announcement::factory()->create()))->assertUnprocessable();
        $this->post(route('announcements.acknowledge', Announcement::factory()->draft()->create(['requires_acknowledgement' => true])))->assertForbidden();
        $this->post(route('announcements.acknowledge', Announcement::factory()->scheduled()->create(['requires_acknowledgement' => true])))->assertForbidden();
        $this->assertDatabaseCount('announcement_receipts', 0);
        $item = Announcement::factory()->create(['requires_acknowledgement' => true]);
        $this->post(route('announcements.acknowledge', $item))->assertRedirect();
        $this->assertNotNull(AnnouncementReceipt::sole()->acknowledged_at);
    }

    public function test_live_immutable_fields_reject_atomically_but_body_edits_keep_receipts_and_private_files_are_cleaned(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('announcements.store'), $this->payload(['attachment' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf')]))->assertRedirect();
        $item = Announcement::sole();
        $path = $item->attachment_path;
        Storage::disk('local')->assertExists($path);
        $this->get(route('announcements.show', $item))->assertOk();
        $read = AnnouncementReceipt::sole()->read_at;
        $this->post(route('announcements.update', $item), $this->payload(['requires_acknowledgement' => true, 'title' => 'Forbidden update']))->assertSessionHasErrors('requires_acknowledgement');
        $this->assertSame('Pengumuman uji', $item->fresh()->title);
        $this->post(route('announcements.update', $item), $this->payload(['body' => 'Isi baru', 'attachment' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')]))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists($item->fresh()->attachment_path);
        $this->assertTrue($read->equalTo(AnnouncementReceipt::sole()->read_at));
        $this->delete(route('announcements.destroy', $item))->assertRedirect();
        $this->assertSame([], Storage::disk('local')->allFiles('announcements'));
        $this->assertDatabaseCount('announcement_receipts', 0);
    }

    public function test_upload_is_cleaned_when_auditing_rolls_back_the_announcement(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new RuntimeException('Audit unavailable')));
        $this->actingAs($admin)->post(route('announcements.store'), $this->payload(['attachment' => UploadedFile::fake()->create('rollback.pdf', 10, 'application/pdf')]))->assertServerError();
        $this->assertDatabaseCount('announcements', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('announcements'));
    }

    public function test_recipient_report_is_per_student_and_only_guardian_acknowledgement_counts(): void
    {
        $f = $this->classroom();
        foreach (Student::factory()->count(29)->create() as $student) {
            Enrollment::create(['student_id' => $student->id, 'class_id' => $f['class']->id, 'started_on' => '2026-07-01']);
        }
        $item = Announcement::factory()->create(['audience_type' => 'class', 'class_id' => $f['class']->id, 'academic_year_id' => $f['year']->id, 'created_by_user_id' => $f['teacherUser']->id, 'requires_acknowledgement' => true]);
        $this->actingAs($f['studentUser'])->post(route('announcements.acknowledge', $item))->assertRedirect();
        $this->actingAs($f['teacherUser'])->get(route('announcements.recipients', $item))->assertInertia(fn (Assert $page) => $page->where('recipientReport.rows.total', 30)->where('recipientReport.totals.acknowledged', 0));
        $this->actingAs($f['parent'])->post(route('announcements.acknowledge', $item))->assertRedirect();
        $this->actingAs($f['teacherUser'])->get(route('announcements.recipients', $item, ['status' => 'unacknowledged']))->assertInertia(fn (Assert $page) => $page->where('recipientReport.totals.acknowledged', 1));
    }

    public function test_grade_targets_do_not_drift_to_a_new_academic_year_and_no_active_year_keeps_all_role_usable(): void
    {
        $f = $this->classroom();
        $item = Announcement::factory()->create(['audience_type' => 'grade_level', 'audience_grade_level' => 5, 'academic_year_id' => $f['year']->id]);
        $this->actingAs($f['parent'])->get(route('announcements.show', $item))->assertOk();
        $f['student']->enrollments()->update(['ended_on' => '2026-10-23']);
        $f['year']->update(['is_active' => false]);
        $year = AcademicYear::factory()->create(['is_active' => true]);
        $class = SchoolClass::factory()->create(['academic_year_id' => $year->id, 'grade_level' => 5]);
        Enrollment::create(['student_id' => $f['student']->id, 'class_id' => $class->id, 'started_on' => '2026-10-24']);
        $this->get(route('announcements.show', $item))->assertForbidden();
        AcademicYear::query()->update(['is_active' => false]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('announcements.store'), $this->payload(['audience_type' => 'grade_level', 'audience_grade_level' => 5]))->assertSessionHasErrors('audience_type');
        $this->post(route('announcements.update', $item), $this->payload(['audience_type' => 'grade_level', 'audience_grade_level' => 5, 'published_at' => '2026-10-24T09:59:00', 'body' => 'Koreksi arsip']))->assertRedirect();
        $this->assertSame('Koreksi arsip', $item->fresh()->body);
        $this->assertSame($f['year']->id, $item->fresh()->academic_year_id);
        $this->post(route('announcements.store'), $this->payload())->assertRedirect();
    }

    public function test_multi_role_manager_permission_is_independent_of_active_role(): void
    {
        $user = User::factory()->withRoles(UserRole::Staff, UserRole::Principal)->create();
        $this->actingAs($user)->withSession(['active_role' => 'staff'])->get(route('announcements.manage'))->assertOk();
        $this->post(route('announcements.store'), $this->payload())->assertRedirect();
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_class_context_hides_another_authors_draft_and_rejects_route_target_tampering(): void
    {
        $f = $this->classroom();
        $own = Announcement::factory()->draft()->create(['class_id' => $f['class']->id, 'academic_year_id' => $f['year']->id, 'audience_type' => 'class', 'created_by_user_id' => $f['teacherUser']->id]);
        $foreign = Announcement::factory()->draft()->create(['class_id' => $f['class']->id, 'academic_year_id' => $f['year']->id, 'audience_type' => 'class']);
        $this->actingAs($f['teacherUser'])->get(route('classes.announcements.index', $f['class']))->assertInertia(fn (Assert $page) => $page->has('announcements.data', 1)->where('announcements.data.0.id', $own->id));
        $this->get(route('announcements.show', $foreign))->assertForbidden();
        $otherClass = SchoolClass::factory()->create(['academic_year_id' => $f['year']->id]);
        $this->post(route('classes.announcements.store', $otherClass), $this->payload(['audience_type' => 'class', 'class_id' => $f['class']->id]))->assertForbidden();
        $this->post(route('classes.announcements.store', $f['class']), $this->payload(['audience_type' => 'all']))->assertForbidden();
        $this->post(route('classes.announcements.store', $f['class']), $this->payload(['audience_type' => 'class', 'class_id' => $f['class']->id, 'notify_urgent' => true]))->assertForbidden();
        $this->assertDatabaseCount('announcements', 2);
    }

    public function test_validates_target_upload_and_dates_without_writing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('announcements.store'), $this->payload(['audience_role' => 'staff']))->assertSessionHasErrors('audience_role');
        $this->post(route('announcements.store'), $this->payload(['attachment' => UploadedFile::fake()->create('program.php', 1, 'text/plain')]))->assertSessionHasErrors('attachment');
        $this->post(route('announcements.store'), $this->payload(['attachment' => UploadedFile::fake()->create('too-big.pdf', 5121, 'application/pdf')]))->assertSessionHasErrors('attachment');
        $this->getJson(route('announcements.index', ['from' => '2026-10-25', 'to' => '2026-10-24']))->assertUnprocessable();
        $this->get(route('announcements.index', ['to' => '2026-10-24']))->assertOk();
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_parent_with_children_in_two_classes_receives_union_and_report_filters_preserve_accountless_rows(): void
    {
        $f = $this->classroom();
        $class = SchoolClass::factory()->create(['academic_year_id' => $f['year']->id, 'grade_level' => 6]);
        $sibling = Student::factory()->create();
        Enrollment::create(['student_id' => $sibling->id, 'class_id' => $class->id, 'started_on' => '2026-07-01']);
        $f['guardian']->students()->attach($sibling, ['relationship_type' => 'father']);
        $noAccount = Guardian::factory()->create(['user_id' => null]);
        $noAccount->students()->attach($sibling, ['relationship_type' => 'mother']);
        $item = Announcement::factory()->create(['audience_type' => 'class', 'class_id' => $class->id, 'academic_year_id' => $f['year']->id, 'requires_acknowledgement' => true]);
        $other = Announcement::factory()->create(['audience_type' => 'class', 'class_id' => $f['class']->id, 'academic_year_id' => $f['year']->id]);
        $this->actingAs($f['parent'])->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->has('announcements.data', 2));
        $this->post(route('announcements.acknowledge', $item))->assertRedirect();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('announcements.recipients', $item))->assertInertia(fn (Assert $page) => $page->where('recipientReport.totals.accountless_guardians', 1)->where('recipientReport.rows.data.0.acknowledged', true));
        $this->get(route('announcements.recipients', ['announcement' => $item, 'status' => 'unacknowledged']))->assertInertia(fn (Assert $page) => $page->where('recipientReport.rows.total', 0));
    }

    public function test_removing_publication_time_from_a_live_announcement_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $item = Announcement::factory()->create(['published_at' => now()]);
        $this->actingAs($admin)->post(route('announcements.update', $item), $this->payload(['published_at' => null]))->assertSessionHasErrors('published_at');
        $this->assertNotNull($item->fresh()->published_at);
    }
}
