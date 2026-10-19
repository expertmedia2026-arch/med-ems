<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\NurseDuty;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NurseRosterService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function indexData(User $viewer, string $weekStart, array $filters = []): array
    {
        $week = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $week->copy()->addDays(6);
        $isManager = $viewer->hasRole(Role::Admin, Role::ChiefNurse);

        $nurseQuery = User::ofRole(Role::Nurse)
            ->with('department')
            ->where('is_active', true)
            ->when(! $isManager, fn ($query) => $query->whereKey($viewer->id))
            ->when($isManager && filled($filters['department'] ?? null), fn ($query) => $query->where('department_id', (int) $filters['department']))
            ->when($isManager && filled($filters['search'] ?? null), function ($query) use ($filters) {
                $search = mb_strtolower(trim((string) $filters['search']));
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->orderBy('name');

        $nurses = $nurseQuery->paginate(10)->withQueryString();

        $duties = NurseDuty::query()
            ->whereIn('nurse_id', $nurses->getCollection()->modelKeys())
            ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->groupBy('nurse_id');

        $scheduledShifts = NurseDuty::query()
            ->when(! $isManager, fn ($query) => $query->where('nurse_id', $viewer->id))
            ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
            ->count();

        $coverage = NurseDuty::query()
            ->selectRaw('duty_date, shift, COUNT(*) as total')
            ->when(! $isManager, fn ($query) => $query->where('nurse_id', $viewer->id))
            ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
            ->groupBy('duty_date', 'shift')
            ->get()
            ->keyBy(fn ($duty) => Carbon::parse($duty->duty_date)->toDateString().':'.$duty->shift);

        $days = collect(self::DAYS)->map(fn ($name, $index) => [
            'name' => $name,
            'date' => $week->copy()->addDays($index),
            'number' => $index + 1,
        ]);

        return [
            'nurses' => $nurses,
            'duties' => $duties,
            'days' => $days,
            'weekStart' => $week,
            'weekEnd' => $weekEnd,
            'isManager' => $isManager,
            'departments' => Department::orderBy('name')->get(),
            'scheduledShifts' => $scheduledShifts,
            'coverage' => $coverage,
            'filters' => ['department' => $filters['department'] ?? '', 'search' => $filters['search'] ?? ''],
        ];
    }

    public function saveWeek(User $nurse, array $data, User $assignedBy): void
    {
        abort_unless($assignedBy->hasRole(Role::Admin, Role::ChiefNurse), 403);
        abort_unless($nurse->hasRole(Role::Nurse), 404, 'Only nurses can be assigned to a nurse roster.');

        $week = Carbon::parse($data['week_start'])->startOfWeek(Carbon::MONDAY);
        $weekEnd = $week->copy()->addDays(6);
        $morningDays = collect($data['morning_days'] ?? [])->map(fn ($day) => (int) $day)->unique();
        $nightDays = collect($data['night_days'] ?? [])->map(fn ($day) => (int) $day)->unique();

        abort_if($morningDays->intersect($nightDays)->isNotEmpty(), 422, 'A nurse cannot be assigned both shifts on the same day.');
        abort_if($morningDays->merge($nightDays)->contains(fn ($day) => $day < 1 || $day > 7), 422, 'Choose a valid day of the week.');

        DB::transaction(function () use ($nurse, $assignedBy, $week, $weekEnd, $morningDays, $nightDays) {
            User::query()->lockForUpdate()->findOrFail($nurse->id);
            NurseDuty::query()->where('nurse_id', $nurse->id)
                ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
                ->delete();

            foreach ([['days' => $morningDays, 'shift' => 'morning'], ['days' => $nightDays, 'shift' => 'night']] as $assignment) {
                foreach ($assignment['days'] as $day) {
                    NurseDuty::create([
                        'nurse_id' => $nurse->id,
                        'duty_date' => $week->copy()->addDays($day - 1)->toDateString(),
                        'shift' => $assignment['shift'],
                        'assigned_by' => $assignedBy->id,
                    ]);
                }
            }
        });
    }
}
