<?php

namespace Tests\Unit\Models;

use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_relation_links_to_employee_profile(): void
    {
        $teacher = Teacher::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $teacher->employee());
        $this->assertInstanceOf(Employee::class, $teacher->employee()->getRelated());
    }

    public function test_classes_relation_resolves_homeroom_classes(): void
    {
        $teacher = Teacher::factory()->make();

        $this->assertInstanceOf(HasMany::class, $teacher->classes());
        $this->assertInstanceOf(SchoolClass::class, $teacher->classes()->getRelated());
    }
}
