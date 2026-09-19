<?php

namespace Tests\Unit\Models;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    public function test_user_relation_links_to_the_login_account(): void
    {
        $guardian = Guardian::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $guardian->user());
        $this->assertInstanceOf(User::class, $guardian->user()->getRelated());
    }

    public function test_students_relation_resolves_children(): void
    {
        $guardian = Guardian::factory()->make();

        $this->assertInstanceOf(BelongsToMany::class, $guardian->students());
        $this->assertInstanceOf(Student::class, $guardian->students()->getRelated());
    }

    public function test_factory_defaults_have_a_unique_phone_number(): void
    {
        $first = Guardian::factory()->make();
        $second = Guardian::factory()->make();

        $this->assertNotNull($first->phone_number);
        $this->assertNotSame($first->phone_number, $second->phone_number);
    }
}
