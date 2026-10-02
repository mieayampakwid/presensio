<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $username
 * @property string|null $email
 * @property string $password
 * @property-read UserRole $role
 * @property bool $is_active
 * @property string|null $locale
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Teacher|null $teacher
 * @property-read Guardian|null $guardian
 * @property-read Student|null $student
 */
#[Fillable(['username', 'email', 'password', 'is_active', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<UserRoleGrant, $this> */
    public function roleGrants(): HasMany
    {
        return $this->hasMany(UserRoleGrant::class);
    }

    /**
     * @return Collection<int, UserRole>
     */
    /**
     * @deprecated Use activeRole() or hasRole()
     *
     * @return Attribute<UserRole, never>
     */
    public function role(): Attribute
    {
        return Attribute::get(fn (): UserRole => $this->activeRole());
    }

    /**
     * @return Collection<int, UserRole>
     */
    public function roles(): Collection
    {
        $held = $this->roleGrants->pluck('role');

        $order = array_flip(array_map(fn (UserRole $case) => $case->value, UserRole::cases()));

        return $held->unique()->sortBy(fn (UserRole $role) => $order[$role->value])->values();
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->roles()->contains($role);
    }

    public function hasAnyRole(UserRole ...$roles): bool
    {
        $held = $this->roles();

        foreach ($roles as $role) {
            if ($held->contains($role)) {
                return true;
            }
        }

        return false;
    }

    public function activeRole(): UserRole
    {
        $roles = $this->roles();
        $sessionRole = session('active_role');

        if ($sessionRole !== null) {
            $parsed = is_string($sessionRole) ? UserRole::tryFrom($sessionRole) : ($sessionRole instanceof UserRole ? $sessionRole : null);
            if ($parsed !== null && $roles->contains($parsed)) {
                return $parsed;
            }
        }

        return $roles->first() ?? UserRole::Teacher;
    }

    /** @return HasOne<Teacher, $this> */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /** @return HasOne<Guardian, $this> */
    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /** @return HasOne<Student, $this> */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Key used by the password broker's token repository. Falls back to the
     * username when no email is on file (spec 01 §5).
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email ?? $this->username;
    }

    /**
     * Send the password reset notification (custom: username-based URL).
     * Email-only — reset-over-WhatsApp stayed out of scope in spec 05,
     * which delivered the WhatsApp channel for absence alerts instead.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Contact resolution for password recovery (spec 15 §Profiles, spec 01 §5):
     * order teacher profile → guardian profile → users.email.
     */
    public function passwordResetContact(): ?string
    {
        $teacher = $this->teacher;
        if ($teacher instanceof Teacher && $teacher->phone_number !== null) {
            return $teacher->phone_number;
        }

        $guardian = $this->guardian;
        if ($guardian instanceof Guardian) {
            return $guardian->phone_number;
        }

        return $this->email;
    }
}
