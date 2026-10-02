<?php

namespace App\Http\Requests\Attendance;

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

        if ($user === null || ! $user->can('override-attendance')) {
            return false;
        }

        return ClassAccess::canWrite($user, $this->integer('class_id'));
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
