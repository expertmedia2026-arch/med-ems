<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNurseRosterRequest;
use App\Models\Department;
use App\Models\StaffDuty;
use App\Models\User;
use App\Services\NurseRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffRosterController extends Controller
{
    public function index(Request $request, NurseRosterService $roster): array
    {
        $filters = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        if (isset($filters['department'])) {
            abort_unless(Department::query()->whereKey($filters['department'])->exists(), 404);
        }

        $weekStart = Carbon::parse($filters['week'] ?? now()->toDateString())
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();
        $data = $roster->indexData($request->user(), $weekStart, $filters);
        $staffPage = $data['staff']->through(fn (User $staff) => [
            'id' => $staff->id,
            'name' => $staff->name,
            'role' => $staff->role?->value,
            'department' => $staff->department ? [
                'id' => $staff->department->id,
                'name' => $staff->department->name,
            ] : null,
            'duties' => $this->dutyData($data['duties']->get($staff->id, collect())),
        ]);

        return [
            'week_start' => $data['weekStart']->toDateString(),
            'week_end' => $data['weekEnd']->toDateString(),
            'is_manager' => $data['isManager'],
            'scheduled_shifts' => $data['scheduledShifts'],
            'staff' => $staffPage,
            'my_duties' => $this->dutyData($data['myDuties']),
            'coworkers' => $data['coworkers']->map(fn ($duties) => $duties->map(fn (StaffDuty $duty) => [
                'id' => $duty->staffMember?->id,
                'name' => $duty->staffMember?->name,
                'role' => $duty->staffMember?->role?->value,
                'date' => $duty->duty_date?->toDateString(),
                'shift' => $duty->shift,
            ])->values()),
            'days' => $data['days']->map(fn (array $day) => [
                'name' => $day['name'],
                'date' => $day['date']->toDateString(),
                'number' => $day['number'],
            ])->values(),
            'departments' => $data['departments']->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
            ])->values(),
            'coverage' => $data['coverage']->map(fn ($coverage) => [
                'date' => Carbon::parse($coverage->duty_date)->toDateString(),
                'shift' => $coverage->shift,
                'total' => (int) $coverage->total,
            ])->values(),
            'filters' => $data['filters'],
        ];
    }

    public function update(StoreNurseRosterRequest $request, User $staff, NurseRosterService $roster): array
    {
        $validated = $request->validated();
        $roster->saveWeek($staff, $validated, $request->user());

        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY);
        $duties = StaffDuty::query()
            ->where('user_id', $staff->id)
            ->whereBetween('duty_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->orderBy('duty_date')
            ->get();

        return [
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'role' => $staff->role?->value,
            ],
            'week_start' => $weekStart->toDateString(),
            'duties' => $this->dutyData($duties),
        ];
    }

    /**
     * @param  iterable<StaffDuty>  $duties
     * @return array<int, array{id:int, date:string, day:int, shift:string, assigned_by:?int}>
     */
    private function dutyData(iterable $duties): array
    {
        return collect($duties)->map(fn (StaffDuty $duty) => [
            'id' => $duty->id,
            'date' => $duty->duty_date?->toDateString(),
            'day' => $duty->duty_date?->dayOfWeekIso,
            'shift' => $duty->shift,
            'assigned_by' => $duty->assigned_by,
        ])->values()->all();
    }
}
