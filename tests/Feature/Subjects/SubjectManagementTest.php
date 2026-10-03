<?php

namespace Tests\Feature\Subjects;

use App\Enums\SubjectGroup;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_subjects_list(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create(['name' => 'Matematika', 'code' => 'MAT']);

        $this->actingAs($admin)
            ->get(route('subjects.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('MAT');
    }

    public function test_admin_can_search_subjects_by_name_or_code(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create(['name' => 'Matematika', 'code' => 'MAT']);
        Subject::factory()->create(['name' => 'Fisika Dasar', 'code' => 'FIS']);

        $this->actingAs($admin)
            ->get(route('subjects.index', ['search' => 'Mate']))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika Dasar');

        $this->actingAs($admin)
            ->get(route('subjects.index', ['search' => 'FIS']))
            ->assertOk()
            ->assertSee('Fisika Dasar')
            ->assertDontSee('Matematika');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_subject_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('subjects.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_subject_and_code_is_uppercased(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('subjects.store'), [
                'name' => 'Matematika',
                'code' => 'mat',
                'group' => SubjectGroup::General->value,
                'sort_order' => 1,
                'description' => 'Mata pelajaran matematika dasar',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('subjects.index'));

        $subject = Subject::where('name', 'Matematika')->first();

        $this->assertNotNull($subject);
        $this->assertSame('MAT', $subject->code);
        $this->assertSame(SubjectGroup::General, $subject->group);
        $this->assertSame(1, $subject->sort_order);
        $this->assertTrue($subject->is_active);
    }

    /**
     * AC-09-01: Creating a second subject with an existing code or name returns HTTP 422.
     */
    public function test_ac_09_01_duplicate_code_or_name_rejected_with_422(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create([
            'name' => 'Matematika',
            'code' => 'MAT',
        ]);

        // Duplicate name
        $this->actingAs($admin)
            ->post(route('subjects.store'), [
                'name' => 'Matematika',
                'code' => 'MTK',
                'group' => SubjectGroup::General->value,
            ])
            ->assertSessionHasErrors('name');

        // Duplicate code (case-insensitive because code is uppercased)
        $this->actingAs($admin)
            ->post(route('subjects.store'), [
                'name' => 'Matematika Terapan',
                'code' => 'mat',
                'group' => SubjectGroup::General->value,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Subject::count());
    }

    public function test_admin_can_update_a_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create([
            'name' => 'Matematika',
            'code' => 'MAT',
            'group' => SubjectGroup::General,
        ]);

        $this->actingAs($admin)
            ->put(route('subjects.update', $subject), [
                'name' => 'Matematika Wajib',
                'code' => 'mat-w',
                'group' => SubjectGroup::General->value,
                'sort_order' => 2,
                'description' => 'Matematika wajib kelas umum',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('subjects.index'));

        $subject->refresh();
        $this->assertSame('Matematika Wajib', $subject->name);
        $this->assertSame('MAT-W', $subject->code);
        $this->assertFalse($subject->is_active);
    }

    public function test_admin_can_delete_a_subject_when_not_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($admin)
            ->delete(route('subjects.destroy', $subject))
            ->assertRedirect(route('subjects.index'));

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    /**
     * AC-09-06: Deleting a subject referenced by any class_subjects row returns HTTP 422.
     */
    public function test_ac_09_06_deleting_a_subject_referenced_by_class_subjects_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();

        // If class_subjects table does not exist yet (during early Task 1), create a mock/temporary structure or test with table
        if (! Schema::hasTable('class_subjects')) {
            Schema::create('class_subjects', function ($table) {
                $table->id();
                $table->foreignId('class_id');
                $table->foreignId('subject_id');
                $table->foreignId('teacher_id');
                $table->decimal('passing_threshold', 5, 2)->default(75.00);
                $table->timestamps();
            });
        }

        $class = SchoolClass::factory()->create();
        $teacher = Teacher::factory()->create();

        DB::table('class_subjects')->insert([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'passing_threshold' => 75.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('subjects.destroy', $subject))
            ->assertStatus(422);

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'principal' => ['principal'],
            'teacher' => ['teacher'],
            'counselor' => ['counselor'],
            'finance' => ['finance'],
            'staff' => ['staff'],
            'parent' => ['parent'],
            'student' => ['student'],
        ];
    }
}
