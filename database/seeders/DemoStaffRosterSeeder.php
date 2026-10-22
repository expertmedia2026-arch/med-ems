<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\StaffDuty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoStaffRosterSeeder extends Seeder
{
    public function run(): void
    {
        $chiefNurse = User::firstOrCreate(
            ['email' => 'chief.nurse@hms.test'],
            ['name' => 'Chief Nurse Demo', 'role' => Role::ChiefNurse, 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()],
        );
        $admin = User::where('role', Role::Admin->value)->orderBy('id')->firstOrFail();
        $chiefPharmacist = User::where('role', Role::ChiefPharmacist->value)->orderBy('id')->first();

        foreach (Department::query()->get() as $department) {
            $head = $department->name === Department::PHARMACY_NAME
                ? $chiefPharmacist
                : $department->users()->where('role', Role::Doctor->value)->orderBy('id')->first();

            if ($head) {
                $department->update(['hod_id' => $head->id]);
            }
        }

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $staff = User::query()
            ->where('is_active', true)
            ->where('role', '!=', Role::Patient->value)
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->get();

        foreach ($staff as $member) {
            StaffDuty::query()->where('user_id', $member->id)
                ->whereBetween('duty_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->delete();

            if ($member->hasRole(Role::Doctor)) {
                $day = (($member->id - 1) % 7) + 1;
                StaffDuty::create([
                    'user_id' => $member->id,
                    'duty_date' => $weekStart->copy()->addDays($day - 1)->toDateString(),
                    'shift' => '24_hour',
                    'assigned_by' => $admin->id,
                ]);

                continue;
            }

            $firstDay = ($member->id % 7) + 1;
            $secondDay = ($firstDay % 7) + 1;
            $firstShift = $member->id % 2 === 0 ? 'morning' : 'night';

            foreach ([[$firstDay, $firstShift], [$secondDay, $firstShift === 'morning' ? 'night' : 'morning']] as [$day, $shift]) {
                StaffDuty::create([
                    'user_id' => $member->id,
                    'duty_date' => $weekStart->copy()->addDays($day - 1)->toDateString(),
                    'shift' => $shift,
                    'assigned_by' => $admin->id,
                ]);
            }
        }

        // Ensure the demo chief nurse account is visible as a roster manager even though it has no department.
        $chiefNurse->forceFill(['is_active' => true])->save();
    }
}
