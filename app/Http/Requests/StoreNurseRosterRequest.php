<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNurseRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staffMember = $this->route('staff');

        $manager = $this->user();
        if (! $manager?->canManageStaffRoster() || ! $staffMember instanceof User || ! $staffMember->is_active || $staffMember->hasRole(Role::Patient)) {
            return false;
        }

        if ($manager->hasRole(Role::Admin)) {
            return true;
        }

        if ($manager->hasRole(Role::ChiefNurse)) {
            return $staffMember->hasRole(Role::Nurse);
        }

        return $manager->department_id !== null && $staffMember->department_id === $manager->department_id;
    }

    public function rules(): array
    {
        $staffMember = $this->route('staff');
        $isDoctor = $staffMember instanceof User && $staffMember->hasRole(Role::Doctor);

        return [
            'week_start' => ['required', 'date_format:Y-m-d'],
            'doctor_day' => [$isDoctor ? 'sometimes' : 'prohibited', 'nullable', 'integer', Rule::in(range(1, 7))],
            'morning_days' => [$isDoctor ? 'prohibited' : 'sometimes', 'array'],
            'morning_days.*' => ['integer', Rule::in(range(1, 7))],
            'night_days' => [$isDoctor ? 'prohibited' : 'sometimes', 'array'],
            'night_days.*' => ['integer', Rule::in(range(1, 7))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('week_start')) {
                return;
            }

            if (! Carbon::createFromFormat('Y-m-d', $this->string('week_start')->toString())->isMonday()) {
                $validator->errors()->add('week_start', 'Choose a Monday to roster a full week.');
            }

            $morningInput = $this->input('morning_days', []);
            $nightInput = $this->input('night_days', []);
            $morningDays = is_array($morningInput) ? array_unique(array_map('intval', $morningInput)) : [];
            $nightDays = is_array($nightInput) ? array_unique(array_map('intval', $nightInput)) : [];
            $overlap = array_intersect($morningDays, $nightDays);

            $overlap = array_values(array_filter($overlap, fn ($day) => $day >= 1 && $day <= 7));
            if ($overlap !== []) {
                $labels = collect($overlap)->map(fn ($day) => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$day - 1])->join(', ');
                $validator->errors()->add('night_days', "A staff member can work only one 12-hour shift per day. Remove the overlapping day(s): {$labels}.");
            }
        }];
    }
}
