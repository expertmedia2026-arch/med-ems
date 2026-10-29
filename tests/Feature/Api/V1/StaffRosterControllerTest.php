<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\StaffDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffRosterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_staff_roster_api_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/nursing/roster')->assertUnauthorized();
    }

    public function test_admin_can_view_weekly_roster_and_assign_staff_shifts(): void
    {
        $hospital = $this->createHospital();
        $department = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Nursing',
        ]);
        $admin = $this->createStaff($hospital, $department, Role::Admin);
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/nursing/roster?week=2026-10-28&department='.$department->id)
            ->assertOk()
            ->assertJsonPath('data.week_start', '2026-10-26')
            ->assertJsonPath('data.is_manager', true)
            ->assertJsonFragment(['id' => $admin->id, 'name' => $admin->name])
            ->assertJsonFragment(['id' => $nurse->id, 'name' => $nurse->name])
            ->assertJsonPath('data.days.0.name', 'Monday');

        $this->putJson("/api/v1/nursing/roster/{$nurse->id}", [
            'week_start' => '2026-10-26',
            'morning_days' => [1, 3],
            'night_days' => [5],
        ])
            ->assertOk()
            ->assertJsonPath('data.staff.id', $nurse->id)
            ->assertJsonPath('data.duties.0.date', '2026-10-26')
            ->assertJsonPath('data.duties.0.shift', 'morning');

        $this->assertTrue(StaffDuty::query()
            ->where('user_id', $nurse->id)
            ->whereDate('duty_date', '2026-10-28')
            ->where('shift', 'morning')
            ->where('assigned_by', $admin->id)
            ->exists());
        $this->assertTrue(StaffDuty::query()
            ->where('user_id', $nurse->id)
            ->whereDate('duty_date', '2026-10-30')
            ->where('shift', 'night')
            ->where('assigned_by', $admin->id)
            ->exists());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_chief_nurse_can_only_assign_nurses_and_doctors_get_one_24_hour_shift(): void
    {
        $hospital = $this->createHospital();
        $department = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Nursing',
        ]);
        $chiefNurse = $this->createStaff($hospital, $department, Role::ChiefNurse);
        $doctor = $this->createStaff($hospital, $department, Role::Doctor);
        Sanctum::actingAs($chiefNurse);

        $this->putJson("/api/v1/nursing/roster/{$doctor->id}", [
            'week_start' => '2026-10-26',
            'doctor_day' => 2,
        ])->assertForbidden();

        $admin = $this->createStaff($hospital, $department, Role::Admin);
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/nursing/roster/{$doctor->id}", [
            'week_start' => '2026-10-26',
            'doctor_day' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.duties.0.date', '2026-10-27')
            ->assertJsonPath('data.duties.0.shift', '24_hour');

        $this->assertTrue(
            StaffDuty::query()
                ->where('user_id', $doctor->id)
                ->whereDate('duty_date', '2026-10-27')
                ->where('shift', '24_hour')
                ->exists(),
        );
    }

    public function test_rejects_overlapping_shifts_and_non_monday_week_starts(): void
    {
        $hospital = $this->createHospital();
        $department = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Nursing',
        ]);
        $admin = $this->createStaff($hospital, $department, Role::Admin);
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/nursing/roster/{$nurse->id}", [
            'week_start' => '2026-10-26',
            'morning_days' => [1],
            'night_days' => [1],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['night_days']);

        $this->putJson("/api/v1/nursing/roster/{$nurse->id}", [
            'week_start' => '2026-10-27',
            'morning_days' => [1],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start']);

        $this->assertDatabaseCount('staff_duties', 0);
    }

    public function test_staff_cannot_view_roster_data_from_another_hospital(): void
    {
        $hospital = $this->createHospital();
        $department = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Nursing',
        ]);
        $staff = $this->createStaff($hospital, $department, Role::Nurse);

        $otherHospital = $this->createHospital('other-roster-hospital');
        $otherDepartment = Department::create([
            'hospital_id' => $otherHospital->id,
            'name' => 'Nursing',
        ]);
        $otherStaff = $this->createStaff($otherHospital, $otherDepartment, Role::Nurse);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/nursing/roster')
            ->assertOk()
            ->assertJsonPath('data.staff.data.0.id', $staff->id);

        $this->putJson("/api/v1/nursing/roster/{$otherStaff->id}", [
            'week_start' => '2026-10-26',
            'morning_days' => [1],
        ])->assertNotFound();

        $this->assertDatabaseCount('staff_duties', 0);
    }

    private function createHospital(string $slug = 'roster-test-hospital'): Hospital
    {
        $hospital = Hospital::create([
            'name' => 'Roster Test Hospital',
            'slug' => $slug,
            'is_active' => true,
        ]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'staff_roster',
            'enabled' => true,
        ]);

        return $hospital;
    }

    private function createStaff(Hospital $hospital, Department $department, Role $role): User
    {
        return User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
