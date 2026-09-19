<?php

namespace App\Http\Requests\Attendance;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Services\Attendance\ClassAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! in_array($user->role, [UserRole::Admin, UserRole::Teacher], true)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return ClassAccess::canAccess($user, $this->integer('class_id'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', Rule::exists(SchoolClass::class, 'id')],
            'date' => ['required', 'date'],
        ];
    }
}
