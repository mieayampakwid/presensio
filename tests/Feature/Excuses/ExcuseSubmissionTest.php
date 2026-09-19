<?php

namespace Tests\Feature\Excuses;

use App\Enums\ExcuseStatus;
use App\Enums\ExcuseType;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExcuseSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jakarta is already past midnight on Monday the 21st while UTC
        // still says Sunday the 20th — pins the school-tz default date.
        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function parentUser(): array
    {
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        return [$user, $guardian];
    }

    private function childFor(Guardian $guardian, array $attributes = []): Student
    {
        $student = Student::factory()->create($attributes);
        $guardian->students()->attach($student->id);

        return $student;
    }

    public function test_parent_submits_an_excuse_and_it_starts_pending(): void
    {
        [$user, $guardian] = $this->parentUser();
        $child = $this->childFor($guardian);

        $this->actingAs($user)
            ->post(route('excuses.store'), [
                'student_id' => $child->id,
                'type' => 'sick',
                'start_date' => '2026-09-21',
                'end_date' => '2026-09-22',
                'reason' => 'Fever since Sunday night.',
            ])
            ->assertRedirect(route('excuses.my'));

        $excuse = Excuse::query()->sole();
        $this->assertTrue($excuse->status === ExcuseStatus::Pending);
        $this->assertTrue($excuse->type === ExcuseType::Sick);
        $this->assertSame($child->id, $excuse->student_id);
        $this->assertNull($excuse->attachment_path);
        $this->assertNull($excuse->reviewed_by_user_id);
    }

    public function test_submission_with_proof_stores_it_under_an_opaque_local_path(): void
    {
        Storage::fake('local');

        [$user, $guardian] = $this->parentUser();
        $child = $this->childFor($guardian);

        $this->actingAs($user)
            ->post(route('excuses.store'), [
                'student_id' => $child->id,
                'type' => 'leave',
                'start_date' => '2026-09-25',
                'end_date' => '2026-09-25',
                'reason' => 'Family wedding out of town.',
                'attachment' => UploadedFile::fake()->image('invitation.jpg'),
            ])
            ->assertRedirect(route('excuses.my'));

        $excuse = Excuse::query()->sole();
        $this->assertMatchesRegularExpression('#^excuses/[0-9a-f]{40}\.jpg$#', (string) $excuse->attachment_path);
        Storage::disk('local')->assertExists((string) $excuse->attachment_path);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        [$user, $guardian] = $this->parentUser();
        $child = $this->childFor($guardian);

        $this->actingAs($user)
            ->post(route('excuses.store'), [
                'student_id' => $child->id,
                'type' => 'sick',
                'start_date' => '2026-09-22',
                'end_date' => '2026-09-21',
                'reason' => 'Backwards range.',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertSame(0, Excuse::count());
    }

    public function test_reason_and_type_are_required(): void
    {
        [$user, $guardian] = $this->parentUser();
        $child = $this->childFor($guardian);

        $this->actingAs($user)
            ->post(route('excuses.store'), [
                'student_id' => $child->id,
                'type' => 'sick',
                'start_date' => '2026-09-21',
                'end_date' => '2026-09-21',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($user)
            ->post(route('excuses.store'), [
                'student_id' => $child->id,
                'type' => 'vacation',
                'start_date' => '2026-09-21',
                'end_date' => '2026-09-21',
                'reason' => 'Not a real category.',
            ])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Excuse::count());
    }

    public function test_attachment_mime_and_size_are_rejected(): void
    {
        Storage::fake('local');

        [$user, $guardian] = $this->parentUser();
        $child = $this->childFor($guardian);

        $base = [
            'student_id' => $child->id,
            'type' => 'sick',
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-21',
            'reason' => 'Doctor note attached.',
        ];

        $this->actingAs($user)
            ->post(route('excuses.store'), $base + [
                'attachment' => UploadedFile::fake()->create('note.txt', 10),
            ])
            ->assertSessionHasErrors('attachment');

        // Config default is 5120 KB — one KB over is rejected.
        $this->actingAs($user)
            ->post(route('excuses.store'), $base + [
                'attachment' => UploadedFile::fake()->create('scan.pdf', 5121),
            ])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, Excuse::count());
    }

    public function test_excuse_for_an_unlinked_student_is_rejected(): void
    {
        [$user, $guardian] = $this->parentUser();
        $outsider = Student::factory()->create();

        $this->actingAs($user)
            ->post(route('excuses.store'), [
                'student_id' => $outsider->id,
                'type' => 'sick',
                'start_date' => '2026-09-21',
                'end_date' => '2026-09-21',
                'reason' => 'Not my child.',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, Excuse::count());
    }

    public function test_pending_and_approved_ranges_overlap_block_but_rejected_and_disjoint_do_not(): void
    {
        [$user, $guardian] = $this->parentUser();
        $child = $this->childFor($guardian);

        $payload = fn (string $start, string $end): array => [
            'student_id' => $child->id,
            'type' => 'sick',
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Overlap probe.',
        ];

        $blocked = Excuse::factory()->create([
            'student_id' => $child->id,
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-23',
        ]);

        // Pending in range — blocked.
        $this->actingAs($user)
            ->post(route('excuses.store'), $payload('2026-09-23', '2026-09-24'))
            ->assertSessionHasErrors('start_date');

        // Approved in range — blocked.
        $blocked->update(['status' => ExcuseStatus::Approved]);
        $this->actingAs($user)
            ->post(route('excuses.store'), $payload('2026-09-19', '2026-09-21'))
            ->assertSessionHasErrors('start_date');

        // Rejected in range — allowed (mind-changes = resubmit).
        $blocked->update(['status' => ExcuseStatus::Rejected]);
        $this->actingAs($user)
            ->post(route('excuses.store'), $payload('2026-09-22', '2026-09-22'))
            ->assertRedirect(route('excuses.my'));

        // Adjacent to the new approved excuse — allowed (inclusive overlap,
        // touching edges do not collide: 24th ends where the 25th starts).
        $this->actingAs($user)
            ->post(route('excuses.store'), $payload('2026-09-25', '2026-09-25'))
            ->assertRedirect(route('excuses.my'));

        $this->assertSame(3, Excuse::count());
    }

    public function test_my_excuses_lists_children_and_submissions(): void
    {
        [$user, $guardian] = $this->parentUser();
        $class = SchoolClass::factory()->create(['name' => 'Kelas 4B']);
        $child = $this->childFor($guardian, ['full_name' => 'Ayu Lestari', 'class_id' => $class->id]);
        $otherChild = $this->childFor($guardian, ['full_name' => 'Budi Santoso']);

        $reviewed = Excuse::factory()->rejected()->create([
            'student_id' => $child->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'review_note' => 'No proof attached.',
        ]);
        $newest = Excuse::factory()->create([
            'student_id' => $otherChild->id,
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-21',
        ]);

        $this->actingAs($user)
            ->get(route('excuses.my'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('excuses/my-excuses')
                ->has('children', 2)
                ->where('children.0.full_name', 'Ayu Lestari')
                ->where('children.0.class_name', 'Kelas 4B')
                ->has('excuses.data', 2)
                // Newest submission first.
                ->where('excuses.data.0.id', $newest->id)
                ->where('excuses.data.1.id', $reviewed->id)
                ->where('excuses.data.1.child_name', 'Ayu Lestari')
                ->where('excuses.data.1.status', 'rejected')
                ->where('excuses.data.1.review_note', 'No proof attached.')
                ->where('excuses.data.1.has_attachment', false)
                ->has('excuses.data.1.attachment_url')
                ->where('excuses.prev_page_url', null));
    }

    public function test_parent_without_guardian_profile_sees_the_empty_state(): void
    {
        $user = User::factory()->parent()->create();

        $this->actingAs($user)
            ->get(route('excuses.my'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('excuses/my-excuses')
                ->where('children', [])
                ->where('excuses', null));
    }

    #[DataProvider('otherRoles')]
    public function test_non_parents_are_forbidden_from_the_guardian_portal(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('excuses.my'))
            ->assertForbidden();
    }

    public static function otherRoles(): array
    {
        return [
            ['admin'],
            ['teacher'],
            ['student'],
        ];
    }
}
