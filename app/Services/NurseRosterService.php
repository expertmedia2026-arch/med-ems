<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\StaffDuty;
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
        $isManager = $viewer->canManageStaffRoster();
        $scopeDepartmentId = $this->managerDepartmentId($viewer);

        $staffQuery = User::with('department')
            ->where('is_active', true)
            ->where('role', '!=', Role::Patient->value)
            ->when(! $isManager, fn ($query) => $query->whereKey($viewer->id))
            ->when($viewer->hasRole(Role::ChiefNurse), fn ($query) => $query->where('role', Role::Nurse->value))
            ->when($scopeDepartmentId !== null, fn ($query) => $query->where('department_id', $scopeDepartmentId))
            ->when($isManager && $scopeDepartmentId === null && filled($filters['department'] ?? null), fn ($query) => $query->where('department_id', (int) $filters['department']))
            ->when($isManager && filled($filters['search'] ?? null), function ($query) use ($filters) {
                $search = mb_strtolower(trim((string) $filters['search']));
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->orderBy('name');

        $staff = $staffQuery->paginate(10)->withQueryString();
        $staffIds = $staff->getCollection()->modelKeys();

        $duties = StaffDuty::query()
            ->whereIn('user_id', $staffIds)
            ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->groupBy('user_id');

        $myDuties = StaffDuty::query()
            ->where('user_id', $viewer->id)
            ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
            ->get();

        $scheduledShifts = $isManager
            ? StaffDuty::query()->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
                ->when($scopeDepartmentId !== null, fn ($query) => $query->whereHas('staffMember', fn ($staffQuery) => $staffQuery->where('department_id', $scopeDepartmentId)))
                ->when($viewer->hasRole(Role::ChiefNurse), fn ($query) => $query->whereHas('staffMember', fn ($staffQuery) => $staffQuery->where('role', Role::Nurse->value)))
                ->count()
            : $myDuties->count();

        $coverage = StaffDuty::query()
            ->selectRaw('staff_duties.duty_date, staff_duties.shift, COUNT(*) as total')
            ->join('users', 'users.id', '=', 'staff_duties.user_id')
            ->whereBetween('staff_duties.duty_date', [$week->toDateString(), $weekEnd->toDateString()])
            ->when($isManager, function ($query) use ($scopeDepartmentId, $viewer) {
                $query->when($scopeDepartmentId !== null, fn ($q) => $q->where('users.department_id', $scopeDepartmentId))
                    ->when($viewer->hasRole(Role::ChiefNurse), fn ($q) => $q->where('users.role', Role::Nurse->value));
            }, fn ($query) => $query->where('users.id', $viewer->id))
            ->groupBy('staff_duties.duty_date', 'staff_duties.shift')
            ->get()
            ->keyBy(fn ($duty) => Carbon::parse($duty->duty_date)->toDateString().':'.$duty->shift);

        $coworkers = collect();
        if (! $isManager && $viewer->department_id) {
            $coworkersQuery = StaffDuty::query()
                ->with('staffMember:id,name,role')
                ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
                ->where('user_id', '!=', $viewer->id)
                ->whereHas('staffMember', fn ($query) => $query->where('department_id', $viewer->department_id)->where('is_active', true));

            $coworkersQuery->where(function ($query) use ($myDuties) {
                foreach ($myDuties as $duty) {
                    $query->orWhere(fn ($sameShift) => $sameShift
                        ->whereDate('duty_date', $duty->duty_date->toDateString())
                        ->where('shift', $duty->shift));
                }
            });

            $coworkers = $coworkersQuery->get()->groupBy(fn ($duty) => $duty->duty_date->toDateString().':'.$duty->shift);
        }

        $days = collect(self::DAYS)->map(fn ($name, $index) => [
            'name' => $name,
            'date' => $week->copy()->addDays($index),
            'number' => $index + 1,
        ]);

        return [
            'viewer' => $viewer,
            'staff' => $staff,
            'duties' => $duties,
            'myDuties' => $myDuties,
            'coworkers' => $coworkers,
            'days' => $days,
            'weekStart' => $week,
            'weekEnd' => $weekEnd,
            'isManager' => $isManager,
            'departments' => Department::query()
                ->when($scopeDepartmentId !== null, fn ($query) => $query->whereKey($scopeDepartmentId))
                ->orderBy('name')->get(),
            'scheduledShifts' => $scheduledShifts,
            'coverage' => $coverage,
            'filters' => ['department' => $filters['department'] ?? '', 'search' => $filters['search'] ?? ''],
        ];
    }

    public function saveWeek(User $staffMember, array $data, User $assignedBy): void
    {
        abort_unless($assignedBy->canManageStaffRoster(), 403);
        abort_unless($staffMember->is_active && ! $staffMember->hasRole(Role::Patient), 404, 'Only active staff can be assigned to a roster.');
        abort_unless($this->canManageMember($assignedBy, $staffMember), 403);

        $week = Carbon::parse($data['week_start'])->startOfWeek(Carbon::MONDAY);
        $weekEnd = $week->copy()->addDays(6);
        $morningDays = collect($data['morning_days'] ?? [])->map(fn ($day) => (int) $day)->unique();
        $nightDays = collect($data['night_days'] ?? [])->map(fn ($day) => (int) $day)->unique();

        abort_if($morningDays->intersect($nightDays)->isNotEmpty(), 422, 'A staff member cannot be assigned both shifts on the same day.');
        abort_if($morningDays->merge($nightDays)->contains(fn ($day) => $day < 1 || $day > 7), 422, 'Choose a valid day of the week.');

        DB::transaction(function () use ($staffMember, $assignedBy, $week, $weekEnd, $morningDays, $nightDays) {
            User::query()->lockForUpdate()->findOrFail($staffMember->id);
            StaffDuty::query()->where('user_id', $staffMember->id)
                ->whereBetween('duty_date', [$week->toDateString(), $weekEnd->toDateString()])
                ->delete();

            foreach ([['days' => $morningDays, 'shift' => 'morning'], ['days' => $nightDays, 'shift' => 'night']] as $assignment) {
                foreach ($assignment['days'] as $day) {
                    StaffDuty::create([
                        'user_id' => $staffMember->id,
                        'duty_date' => $week->copy()->addDays($day - 1)->toDateString(),
                        'shift' => $assignment['shift'],
                        'assigned_by' => $assignedBy->id,
                    ]);
                }
            }
        });
    }

    private function managerDepartmentId(User $manager): ?int
    {
        if ($manager->hasRole(Role::Admin, Role::ChiefNurse)) {
            return null;
        }

        return $manager->department_id;
    }

    private function canManageMember(User $manager, User $staffMember): bool
    {
        if ($manager->hasRole(Role::Admin)) {
            return true;
        }

        if ($manager->hasRole(Role::ChiefNurse)) {
            return $staffMember->hasRole(Role::Nurse);
        }

        return $manager->department_id !== null && $staffMember->department_id === $manager->department_id;
    }
}
