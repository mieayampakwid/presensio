<?php

namespace Tests\Feature\Classes;

use App\Enums\Curriculum;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function schoolWideClassReaderRoles(): array
    {
        return [
            'principal' => ['principal'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('schoolWideClassReaderRoles')]
    public function test_populated_class_list_displays_homeroom_names(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $year = AcademicYear::factory()->current()->create();
        $firstTeacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);
        $secondTeacher = Teacher::factory()->create(['name' => 'Siti Aminah']);
        SchoolClass::factory()->withTeacher($firstTeacher)->create([
            'academic_year_id' => $year->id,
            'name' => '1A',
        ]);
        SchoolClass::factory()->withTeacher($secondTeacher)->create([
            'academic_year_id' => $year->id,
            'name' => '1B',
        ]);
        SchoolClass::factory()->create([
            'academic_year_id' => $year->id,
            'name' => '1C',
        ]);

        $this->actingAs($user)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/index')
                ->has('classes.data', 3)
                ->where('classes.data.0.teacher.name', 'Budi Raharjo')
                ->where('classes.data.1.teacher.name', 'Siti Aminah')
                ->where('classes.data.2.teacher', null)
                ->where('can.manage', $role === 'admin')
            );
    }

    public function test_teacher_populated_class_list_stays_scoped(): void
    {
        $user = User::factory()->teacher()->create();
        $year = AcademicYear::factory()->current()->create();
        $teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'name' => 'Budi Raharjo',
        ]);
        SchoolClass::factory()->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
            'name' => '1A',
        ]);
        SchoolClass::factory()->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
            'name' => '1B',
        ]);
        SchoolClass::factory()->create([
            'academic_year_id' => $year->id,
            'name' => 'Hidden Class',
        ]);

        $this->actingAs($user)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/index')
                ->has('classes.data', 2)
                ->where('classes.data.0.name', '1A')
                ->where('classes.data.0.teacher.name', 'Budi Raharjo')
                ->where('classes.data.1.name', '1B')
                ->where('classes.data.1.teacher.name', 'Budi Raharjo')
                ->where('can.manage', false)
            );
    }

    public function test_admin_can_view_the_classes_list(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create(['name' => 'Kelas 1A']);

        $this->actingAs($admin)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/index')
                ->has('classes.data', 1)
                ->where('classes.data.0.id', $class->id)
            );
    }

    public function test_admin_can_search_classes_by_name_or_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);

        SchoolClass::factory()->withTeacher($teacher)->create(['name' => 'Kelas 1A']);
        SchoolClass::factory()->create(['name' => 'Kelas 2B']);

        $this->actingAs($admin)
            ->get(route('classes.index', ['search' => 'Budi']))
            ->assertOk()
            ->assertSee('Kelas 1A')
            ->assertDontSee('Kelas 2B');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_class_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('classes.index'))
            ->assertForbidden();
    }

    public function test_teacher_can_view_classes_list_but_cannot_mutate(): void
    {
        $teacherUser = User::factory()->teacher()->create();
        $class = SchoolClass::factory()->create();

        $this->actingAs($teacherUser)
            ->get(route('classes.index'))
            ->assertOk();

        $this->actingAs($teacherUser)
            ->post(route('classes.store'), [
                'name' => 'Kelas Baru',
                'grade_level' => 1,
                'curriculum' => 'merdeka',
            ])
            ->assertForbidden();

        $this->actingAs($teacherUser)
            ->put(route('classes.update', $class), ['name' => 'Kelas Edit'])
            ->assertForbidden();

        $this->actingAs($teacherUser)
            ->delete(route('classes.destroy', $class))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_class_with_a_homeroom_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        AcademicYear::factory()->current()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'grade_level' => 1,
                'curriculum' => 'merdeka',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('classes.index'));

        $class = SchoolClass::where('name', 'Kelas 1A')->first();

        $this->assertNotNull($class);
        $this->assertSame(1, $class->grade_level);
        $this->assertSame(Curriculum::Merdeka, $class->curriculum);
        $this->assertSame($teacher->id, $class->teacher_id);
    }

    public function test_admin_can_create_a_class_without_a_homeroom_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        AcademicYear::factory()->current()->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'grade_level' => 1,
                'curriculum' => 'merdeka',
            ])
            ->assertSessionHasNoErrors();

        $class = SchoolClass::where('name', 'Kelas 1A')->first();

        $this->assertNotNull($class);
        $this->assertSame(1, $class->grade_level);
        $this->assertSame(Curriculum::Merdeka, $class->curriculum);
        $this->assertNull($class->teacher_id);
    }

    public function test_class_creation_requires_grade_level_and_curriculum(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
            ])
            ->assertSessionHasErrors(['grade_level', 'curriculum']);

        // Grade level must be 1..12
        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'grade_level' => 0,
                'curriculum' => 'merdeka',
            ])
            ->assertSessionHasErrors('grade_level');

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'grade_level' => 13,
                'curriculum' => 'merdeka',
            ])
            ->assertSessionHasErrors('grade_level');
    }

    public function test_class_creation_requires_an_active_academic_year(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'grade_level' => 1,
                'curriculum' => 'merdeka',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, SchoolClass::count());
    }

    public function test_class_creation_rejects_a_teacher_who_already_homerooms_another_class(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 2B',
                'grade_level' => 2,
                'curriculum' => 'merdeka',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, SchoolClass::where('teacher_id', $teacher->id)->count());
    }

    public function test_homeroom_rule_is_advisable_when_the_config_allows_multiple(): void
    {
        config(['school.allow_multiple_homerooms' => true]);

        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create(['name' => 'Kelas 1A']);

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 2B',
                'grade_level' => 2,
                'curriculum' => 'merdeka',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SchoolClass::where('teacher_id', $teacher->id)->count());
    }

    public function test_class_update_may_keep_its_own_homeroom_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        $class = SchoolClass::factory()->withTeacher($teacher)->create();

        $this->actingAs($admin)
            ->put(route('classes.update', $class), [
                'name' => 'Kelas 1A (updated)',
                'grade_level' => 1,
                'curriculum' => 'merdeka',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Kelas 1A (updated)', $class->fresh()->name);
        $this->assertSame($teacher->id, $class->fresh()->teacher_id);
    }

    public function test_class_update_cannot_take_another_classs_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create(['name' => 'Kelas 1A']);
        $other = SchoolClass::factory()->create(['name' => 'Kelas 2B']);

        $this->actingAs($admin)
            ->put(route('classes.update', $other), [
                'name' => 'Kelas 2B',
                'grade_level' => 2,
                'curriculum' => 'merdeka',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame('Kelas 1A', SchoolClass::where('teacher_id', $teacher->id)->value('name'));
    }

    public function test_class_deletion_is_blocked_while_students_are_enrolled(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        Student::factory()->enrolledIn($class)->create();

        $this->actingAs($admin)
            ->from(route('classes.index'))
            ->delete(route('classes.destroy', $class))
            ->assertRedirect(route('classes.index'));

        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_admin_can_delete_an_empty_class(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();

        $this->actingAs($admin)
            ->delete(route('classes.destroy', $class))
            ->assertRedirect(route('classes.index'));

        $this->assertDatabaseMissing('classes', ['id' => $class->id]);
    }

    public function test_duplicate_class_names_in_the_active_year_are_rejected(): void
    {
        // Classes are year-scoped (spec 02 v2.0): the same name may exist
        // in a different year, never twice in the same one.
        $admin = User::factory()->admin()->create();
        SchoolClass::factory()->create(['name' => 'Kelas 1A']);

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'grade_level' => 1,
                'curriculum' => 'merdeka',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, SchoolClass::where('name', 'Kelas 1A')->count());
    }

    public static function nonAdminRoles(): array
    {
        return [
            'student' => ['student'],
            'parent' => ['parent'],
        ];
    }
}
