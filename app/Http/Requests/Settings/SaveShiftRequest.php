<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Models\Shift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * The sweep offset defaults to 120 minutes when omitted.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('sweep_offset_minutes') === null || $this->input('sweep_offset_minutes') === '') {
            $this->merge(['sweep_offset_minutes' => 120]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Shift|null $shift */
        $shift = $this->route('shift');
        $shiftId = $shift instanceof Shift ? $shift->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(Shift::class, 'name')->ignore($shiftId),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'sweep_offset_minutes' => ['required', 'integer', 'between:30,480'],
        ];
    }

    /**
     * The per-shift sweep fires at start + offset; past 24:00 it would run
     * on the next calendar day and mark tonight's not-yet-started shift
     * absent (spec 20 §Req 1, Review Focus 6).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$hours, $minutes] = array_map(intval(...), explode(':', (string) $this->validated('start_time')));

            if ($hours * 60 + $minutes + (int) $this->validated('sweep_offset_minutes') >= 24 * 60) {
                $validator->errors()->add('sweep_offset_minutes', __('shifts.errors.sweep_past_midnight'));
            }
        });
    }

    /**
     * Attributes ready for persistence; crosses_midnight is derived, never
     * taken from the client.
     *
     * @return array{name: string, start_time: string, end_time: string, crosses_midnight: bool, sweep_offset_minutes: int}
     */
    public function shiftAttributes(): array
    {
        $start = (string) $this->validated('start_time');
        $end = (string) $this->validated('end_time');

        return [
            'name' => (string) $this->validated('name'),
            'start_time' => $start,
            'end_time' => $end,
            'crosses_midnight' => $end < $start,
            'sweep_offset_minutes' => (int) $this->validated('sweep_offset_minutes'),
        ];
    }
}
