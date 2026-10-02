<?php

namespace App\Authorization;

use App\Enums\Ability;
use App\Enums\UserRole;

class RolePermissions
{
    /**
     * @var array<string, list<UserRole>>
     */
    private const MATRIX = [
        Ability::ManageMasterData->value => [
            UserRole::Admin,
        ],
        Ability::OverrideAttendance->value => [
            UserRole::Admin,
            UserRole::Teacher,
        ],
        Ability::ReviewExcuses->value => [
            UserRole::Admin,
        ],
        Ability::ViewAttendanceReports->value => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Teacher,
            UserRole::Counselor,
            UserRole::Parent,
            UserRole::Student,
        ],
        Ability::EnterGrades->value => [
            UserRole::Admin,
            UserRole::Teacher,
        ],
        Ability::ViewGrades->value => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Teacher,
        ],
        Ability::PublishReportCards->value => [
            UserRole::Admin,
            UserRole::Teacher,
        ],
        Ability::ViewReportCards->value => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Teacher,
            UserRole::Parent,
            UserRole::Student,
        ],
        Ability::ManageFees->value => [
            UserRole::Admin,
            UserRole::Finance,
        ],
        Ability::ViewFees->value => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Finance,
            UserRole::Parent,
            UserRole::Student,
        ],
        Ability::ViewAuditLog->value => [
            UserRole::Admin,
            UserRole::Principal,
        ],
        Ability::ManageStaffAttendance->value => [
            UserRole::Admin,
            UserRole::Principal,
        ],
    ];

    /**
     * @return list<UserRole>
     */
    public static function rolesFor(Ability $ability): array
    {
        return self::MATRIX[$ability->value] ?? [];
    }

    /**
     * @return list<Ability>
     */
    public static function grants(UserRole $role): array
    {
        $abilities = [];
        foreach (Ability::cases() as $ability) {
            if (in_array($role, self::rolesFor($ability), true)) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }
}
