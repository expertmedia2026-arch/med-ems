<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_a_tenant_isolated_general_report(): void
    {
        [$hospital, $admin, $department] = $this->hospitalWorkspace('Urology Centre');
        [$otherHospital] = $this->hospitalWorkspace('Other Hospital');

        Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'UC-26-00001',
            'first_name' => 'Chinedu',
            'last_name' => 'Okafor',
            'gender' => 'male',
            'date_of_birth' => '1980-04-12',
            'is_nhis' => true,
            'created_at' => '2026-11-03 09:00:00',
            'updated_at' => '2026-11-03 09:00:00',
        ]);
        Patient::create([
            'hospital_id' => $otherHospital->id,
            'hospital_number' => 'OH-26-00001',
            'first_name' => 'Adaeze',
            'last_name' => 'Eze',
            'gender' => 'female',
            'date_of_birth' => '1990-02-08',
            'is_nhis' => false,
            'created_at' => '2026-11-03 09:00:00',
            'updated_at' => '2026-11-03 09:00:00',
        ]);
        $patient = Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->firstOrFail();
        Visit::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'department_id' => $department->id,
            'type' => 'consultation',
            'status' => 'completed',
            'diagnosis' => 'Benign prostatic hyperplasia',
            'checked_in_at' => '2026-11-05 10:00:00',
            'triaged_at' => '2026-11-05 10:15:00',
            'closed_at' => '2026-11-05 11:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.general', [
            'period' => 'custom',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]));

        $response->assertOk()
            ->assertSee('General performance report')
            ->assertSee('Benign prostatic hyperplasia')
            ->assertViewHas('patientSummary', fn (array $summary) => $summary['registered'] === 1 && $summary['nhis'] === 1)
            ->assertViewHas('clinicalSummary', fn (array $summary) => $summary['visits'] === 1 && $summary['completed'] === 1);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('General Report')
            ->assertSee('General hospital report')
            ->assertSee(route('reports.general'), false);

        $this->actingAs($admin)->get(route('reports.general.export.pdf', [
            'period' => 'custom',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_department_filter_limits_department_owned_activity(): void
    {
        [$hospital, $admin, $urology] = $this->hospitalWorkspace('Specialist Hospital');
        $radiology = Department::create(['hospital_id' => $hospital->id, 'name' => 'Radiology']);
        $patient = Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'SH-26-00001',
            'first_name' => 'Obinna',
            'last_name' => 'Nwosu',
            'gender' => 'male',
            'date_of_birth' => '1975-09-01',
            'is_nhis' => false,
            'created_at' => '2026-11-01 08:00:00',
            'updated_at' => '2026-11-01 08:00:00',
        ]);
        foreach ([$urology, $radiology] as $department) {
            Visit::create([
                'hospital_id' => $hospital->id,
                'patient_id' => $patient->id,
                'doctor_id' => $admin->id,
                'department_id' => $department->id,
                'type' => 'consultation',
                'status' => 'completed',
                'checked_in_at' => '2026-11-05 10:00:00',
            ]);
        }

        $this->actingAs($admin)->get(route('reports.general', [
            'period' => 'custom',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'department_id' => $urology->id,
        ]))->assertOk()
            ->assertViewHas('clinicalSummary', fn (array $summary) => $summary['visits'] === 1)
            ->assertViewHas('selectedDepartment', fn (Department $department) => $department->is($urology));
    }

    public function test_general_report_rejects_unauthorized_roles_and_invalid_ranges(): void
    {
        [, $admin] = $this->hospitalWorkspace('Access Hospital');
        $receptionist = User::factory()->create([
            'hospital_id' => $admin->hospital_id,
            'role' => Role::Receptionist,
            'is_active' => true,
        ]);

        $this->actingAs($receptionist)->get(route('reports.general'))->assertForbidden();

        $this->actingAs($admin)->get(route('reports.general', [
            'period' => 'custom',
            'start_date' => '2026-11-30',
            'end_date' => '2026-11-01',
        ]))->assertSessionHasErrors('end_date');
    }

    /** @return array{Hospital, User, Department} */
    private function hospitalWorkspace(string $name): array
    {
        $hospital = Hospital::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(100, 999),
            'is_active' => true,
        ]);
        HospitalSetting::create(['hospital_id' => $hospital->id, 'name' => $name]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'finance_reports',
            'enabled' => true,
        ]);
        $department = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Urology',
        ]);
        $admin = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        return [$hospital, $admin, $department];
    }
}
