<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_principal_have_access_to_all_academic_classes(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->principal()->create();

        $year1 = AcademicYear::factory()->create();
        $year2 = AcademicYear::factory()->create();

        $class1 = SchoolClass::factory()->create(['academic_year_id' => $year1->id]);
        $class2 = SchoolClass::factory()->create(['academic_year_id' => $year2->id]);

        $this->assertEqualsCanonicalizing(
            [$class1->id, $class2->id],
            ClassAccess::academicClassIds($admin)->all()
        );

        $this->assertEqualsCanonicalizing(
            [$class1->id],
            ClassAccess::academicClassIds($admin, $year1->id)->all()
        );

        $this->assertEqualsCanonicalizing(
            [$class1->id, $class2->id],
            ClassAccess::academicClassIds($principal)->all()
        );
    }

    public function test_teacher_academic_class_ids_unions_homeroom_and_subject_assignments(): void
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $homeroomClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $assignedClass = SchoolClass::factory()->create();
        $otherClass = SchoolClass::factory()->create();

        ClassSubject::factory()->create([
            'class_id' => $assignedClass->id,
            'teacher_id' => $teacher->id,
        ]);

        $academicIds = ClassAccess::academicClassIds($user)->all();

        $this->assertContains($homeroomClass->id, $academicIds);
        $this->assertContains($assignedClass->id, $academicIds);
        $this->assertNotContains($otherClass->id, $academicIds);
    }

    public function test_teacher_without_profile_sees_empty_academic_classes(): void
    {
        $user = User::factory()->teacher()->create(); // No Teacher model linked

        $this->assertEmpty(ClassAccess::academicClassIds($user)->all());
    }

    public function test_can_write_course_permits_admin_and_assigned_teacher_only(): void
    {
        $admin = User::factory()->admin()->create();

        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $otherTeacherUser = User::factory()->teacher()->create();
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);

        $assignment = ClassSubject::factory()->create(['teacher_id' => $teacher->id]);

        // Admin can write
        $this->assertTrue(ClassAccess::canWriteCourse($admin, $assignment));

        // Assigned teacher can write
        $this->assertTrue(ClassAccess::canWriteCourse($teacherUser, $assignment));

        // Other teacher cannot write
        $this->assertFalse(ClassAccess::canWriteCourse($otherTeacherUser, $assignment));
    }
}
