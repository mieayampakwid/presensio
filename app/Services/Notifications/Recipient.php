<?php

namespace App\Services\Notifications;

use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Teacher;
use App\Models\User;

readonly class Recipient
{
    public function __construct(
        public string $type,
        public int $id,
        public ?User $user = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $customKey = null,
    ) {}

    public function key(): string
    {
        return $this->customKey ?? "{$this->type}:{$this->id}";
    }

    public static function fromUser(User $user, ?string $customKey = null): self
    {
        $user->loadMissing(['guardian', 'employee']);

        $phone = null;
        if ($user->guardian !== null) {
            $phone = $user->guardian->phone_number;
        } elseif ($user->employee !== null) {
            $phone = $user->employee->phone_number;
        }

        return new self(
            type: 'user',
            id: $user->id,
            user: $user,
            phone: $phone,
            email: $user->email,
            customKey: $customKey,
        );
    }

    public static function fromGuardian(Guardian $guardian, ?string $customKey = null): self
    {
        $guardian->loadMissing('user');
        $user = $guardian->user;

        return new self(
            type: 'guardian',
            id: $guardian->id,
            user: $user,
            phone: $guardian->phone_number,
            email: $user?->email,
            customKey: $customKey,
        );
    }

    public static function fromEmployee(Teacher|Employee $employee, ?string $customKey = null): self
    {
        if ($employee instanceof Teacher) {
            $employee->loadMissing('employee.user');
            $emp = $employee->employee;
            $user = $emp?->user;

            return new self(
                type: 'employee',
                id: $employee->id,
                user: $user,
                phone: $emp?->phone_number,
                email: $user?->email,
                customKey: $customKey,
            );
        }

        $employee->loadMissing('user');
        $user = $employee->user;

        return new self(
            type: 'employee',
            id: $employee->id,
            user: $user,
            phone: $employee->phone_number,
            email: $user?->email,
            customKey: $customKey,
        );
    }
}
