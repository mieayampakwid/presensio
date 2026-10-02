<?php

namespace App\Authorization;

use App\Enums\Ability;
use App\Enums\UserRole;

class RolePermissions
{
    /**
     * The single source of truth for role-ability mappings (spec 15 §Permissions).
     *
     * @var array<string, list<UserRole>>
     */
    public const MATRIX = [
        'manage-master-data' => [
            UserRole::Admin,
        ],
        'override-attendance' => [
            UserRole::Admin,
            UserRole::Teacher,
        ],
        'review-excuses' => [
            UserRole::Admin,
        ],
        'view-attendance-reports' => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Teacher,
            UserRole::Counselor,
            UserRole::Parent,
            UserRole::Student,
        ],
        'enter-grades' => [
            UserRole::Admin,
            UserRole::Teacher,
        ],
        'view-grades' => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Teacher,
        ],
        'publish-report-cards' => [
            UserRole::Admin,
            UserRole::Teacher,
        ],
        'view-report-cards' => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Teacher,
            UserRole::Parent,
            UserRole::Student,
        ],
        'manage-staff-attendance' => [
            UserRole::Admin,
            UserRole::Principal,
        ],
        'manage-fees' => [
            UserRole::Admin,
            UserRole::Finance,
        ],
        'view-fees' => [
            UserRole::Admin,
            UserRole::Principal,
            UserRole::Finance,
            UserRole::Parent,
            UserRole::Student,
        ],
        'view-audit-log' => [
            UserRole::Admin,
            UserRole::Principal,
        ],
    ];

    /**
     * @return list<UserRole>
     */
    public static function rolesFor(Ability $ability): array
    {
        return self::MATRIX[$ability->value];
    }

    /**
     * @return list<Ability>
     */
    public static function grants(UserRole $role): array
    {
        $abilities = [];

        foreach (self::MATRIX as $abilityValue => $roles) {
            if (in_array($role, $roles, true)) {
                $abilities[] = Ability::from($abilityValue);
            }
        }

        return $abilities;
    }
}
