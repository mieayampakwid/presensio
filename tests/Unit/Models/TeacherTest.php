<?php

namespace Tests\Unit\Models;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    public function test_user_relation_links_to_the_login_account(): void
    {
        $teacher = Teacher::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $teacher->user());
        $this->assertInstanceOf(User::class, $teacher->user()->getRelated());
    }

    public function test_classes_relation_resolves_homeroom_classes(): void
    {
        $teacher = Teacher::factory()->make();

        $this->assertInstanceOf(HasMany::class, $teacher->classes());
        $this->assertInstanceOf(SchoolClass::class, $teacher->classes()->getRelated());
    }

    public function test_factory_defaults_have_master_data_but_no_user(): void
    {
        $teacher = Teacher::factory()->make();

        $this->assertNotNull($teacher->name);
        $this->assertNotNull($teacher->teacher_number);
        $this->assertNull($teacher->user_id);
    }
}
