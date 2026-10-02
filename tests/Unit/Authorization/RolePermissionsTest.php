<?php

namespace Tests\Unit\Authorization;

use App\Authorization\RolePermissions;
use App\Enums\Ability;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<UserRole>  $grantedRoles
     * @param  list<UserRole>  $deniedRoles
     */
    #[DataProvider('matrixProvider')]
    public function test_ability_grants_and_denials_match_matrix(Ability $ability, array $grantedRoles, array $deniedRoles): void
    {
        $rolesForAbility = RolePermissions::rolesFor($ability);

        $this->assertEqualsCanonicalizing($grantedRoles, $rolesForAbility);

        foreach ($grantedRoles as $role) {
            $user = User::factory()->withRoles($role)->create();
            $this->assertTrue(Gate::forUser($user)->allows($ability->value), "Role {$role->value} should be allowed {$ability->value}");
            $this->assertContains($ability, RolePermissions::grants($role));
        }

        foreach ($deniedRoles as $role) {
            $user = User::factory()->withRoles($role)->create();
            $this->assertFalse(Gate::forUser($user)->allows($ability->value), "Role {$role->value} should be denied {$ability->value}");
            $this->assertNotContains($ability, RolePermissions::grants($role));
        }
    }

    public static function matrixProvider(): array
    {
        return [
            'ManageMasterData' => [
                Ability::ManageMasterData,
                [UserRole::Admin],
                [UserRole::Principal, UserRole::Teacher, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'OverrideAttendance' => [
                Ability::OverrideAttendance,
                [UserRole::Admin, UserRole::Teacher],
                [UserRole::Principal, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'ReviewExcuses' => [
                Ability::ReviewExcuses,
                [UserRole::Admin],
                [UserRole::Principal, UserRole::Teacher, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'ViewAttendanceReports' => [
                Ability::ViewAttendanceReports,
                [UserRole::Admin, UserRole::Principal, UserRole::Teacher, UserRole::Counselor, UserRole::Parent, UserRole::Student],
                [UserRole::Finance, UserRole::Staff],
            ],
            'EnterGrades' => [
                Ability::EnterGrades,
                [UserRole::Admin, UserRole::Teacher],
                [UserRole::Principal, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'ViewGrades' => [
                Ability::ViewGrades,
                [UserRole::Admin, UserRole::Principal, UserRole::Teacher],
                [UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'PublishReportCards' => [
                Ability::PublishReportCards,
                [UserRole::Admin, UserRole::Teacher],
                [UserRole::Principal, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'ViewReportCards' => [
                Ability::ViewReportCards,
                [UserRole::Admin, UserRole::Principal, UserRole::Teacher, UserRole::Parent, UserRole::Student],
                [UserRole::Counselor, UserRole::Finance, UserRole::Staff],
            ],
            'ManageFees' => [
                Ability::ManageFees,
                [UserRole::Admin, UserRole::Finance],
                [UserRole::Principal, UserRole::Teacher, UserRole::Counselor, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'ViewFees' => [
                Ability::ViewFees,
                [UserRole::Admin, UserRole::Principal, UserRole::Finance, UserRole::Parent, UserRole::Student],
                [UserRole::Teacher, UserRole::Counselor, UserRole::Staff],
            ],
            'ViewAuditLog' => [
                Ability::ViewAuditLog,
                [UserRole::Admin, UserRole::Principal],
                [UserRole::Teacher, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
            'ManageStaffAttendance' => [
                Ability::ManageStaffAttendance,
                [UserRole::Admin, UserRole::Principal],
                [UserRole::Teacher, UserRole::Counselor, UserRole::Finance, UserRole::Staff, UserRole::Parent, UserRole::Student],
            ],
        ];
    }
}
