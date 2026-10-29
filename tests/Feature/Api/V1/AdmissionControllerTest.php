<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_admission_api_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/admissions')->assertUnauthorized();
    }

    public function test_returns_403_when_a_receptionist_requests_admissions(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Outpatient', false);
        $receptionist = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'role' => Role::Receptionist,
            'is_active' => true,
        ]);
        Sanctum::actingAs($receptionist);

        $this->getJson('/api/v1/admissions')->assertForbidden();
    }

    public function test_doctor_can_admit_an_open_consultation_and_transition_its_visit_to_inpatient(): void
    {
        $hospital = $this->createHospital();
        $ward = $this->createDepartment($hospital, 'Medical Ward', true);
        $doctor = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $ward->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        $patient = $this->createPatient($hospital);
        $visit = Visit::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'consultation',
            'status' => VisitStatus::InConsultation,
            'checked_in_at' => now(),
            'consulted_at' => now(),
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/v1/consultations/{$visit->id}/admit", [
            'department_id' => $ward->id,
            'admission_diagnosis' => 'Pneumonia',
            'admission_reason' => 'Requires inpatient treatment',
            'initial_treatment_plan' => 'Monitor observations and administer prescribed treatment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.admission_diagnosis', 'Pneumonia');
        $this->assertDatabaseHas('admissions', [
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $ward->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => VisitStatus::Inpatient->value,
        ]);
    }

    public function test_doctor_can_list_and_view_admissions_in_their_ward(): void
    {
        $case = $this->createInpatientCase();
        Sanctum::actingAs($case['doctor']);

        $this->getJson('/api/v1/admissions')
            ->assertOk()
            ->assertJsonPath('data.active_admissions.data.0.admission_number', 'ADM-API-TEST');

        $this->getJson("/api/v1/admissions/{$case['admission']->id}")
            ->assertOk()
            ->assertJsonPath('data.admission_number', 'ADM-API-TEST')
            ->assertJsonPath('data.patient.name', 'Avery Patient')
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_doctor_can_discharge_an_active_admission_and_close_its_visit(): void
    {
        $case = $this->createInpatientCase();
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/admissions/{$case['admission']->id}/discharge", [
            'discharge_diagnosis' => 'Pneumonia, improved',
            'discharge_summary' => 'Stable for discharge.',
            'discharge_medications' => 'Complete prescribed course.',
            'follow_up_instructions' => 'Return for review in one week.',
            'patient_discharge_instructions' => 'Return if symptoms worsen.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'discharged');

        $this->assertDatabaseHas('admissions', [
            'id' => $case['admission']->id,
            'status' => 'discharged',
        ]);
        $this->assertDatabaseHas('visits', [
            'id' => $case['visit']->id,
            'status' => VisitStatus::Completed->value,
        ]);
    }

    public function test_returns_404_when_a_doctor_requests_an_admission_from_another_hospital(): void
    {
        $hospital = $this->createHospital();
        $otherHospital = $this->createHospital('other-hospital');
        $ward = $this->createDepartment($hospital, 'Ward A', true);
        $otherWard = $this->createDepartment($otherHospital, 'Ward B', true);
        $doctor = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $ward->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        $patient = $this->createPatient($otherHospital);
        $visit = Visit::create([
            'hospital_id' => $otherHospital->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'consultation',
            'status' => VisitStatus::Inpatient,
            'checked_in_at' => now(),
        ]);
        $admission = Admission::create([
            'hospital_id' => $otherHospital->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $otherWard->id,
            'attending_doctor_id' => $doctor->id,
            'admitted_by' => $doctor->id,
            'status' => 'active',
            'admitted_at' => now(),
            'admission_reason' => 'Test admission',
            'admission_diagnosis' => 'Test diagnosis',
            'initial_treatment_plan' => 'Test plan',
        ]);
        Sanctum::actingAs($doctor);

        $this->getJson("/api/v1/admissions/{$admission->id}")->assertNotFound();
    }

    /**
     * @return array{
     *     hospital: Hospital,
     *     ward: Department,
     *     doctor: User,
     *     patient: Patient,
     *     visit: Visit,
     *     admission: Admission
     * }
     */
    private function createInpatientCase(): array
    {
        $hospital = $this->createHospital();
        $ward = $this->createDepartment($hospital, 'Medical Ward', true);
        $doctor = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $ward->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        $patient = $this->createPatient($hospital);
        $visit = Visit::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'consultation',
            'status' => VisitStatus::Inpatient,
            'checked_in_at' => now(),
            'closed_at' => now(),
        ]);
        $admission = Admission::create([
            'hospital_id' => $hospital->id,
            'admission_number' => 'ADM-API-TEST',
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $ward->id,
            'attending_doctor_id' => $doctor->id,
            'admitted_by' => $doctor->id,
            'status' => 'active',
            'admitted_at' => now(),
            'admission_reason' => 'Test admission',
            'admission_diagnosis' => 'Test diagnosis',
            'initial_treatment_plan' => 'Test plan',
        ]);

        return compact('hospital', 'ward', 'doctor', 'patient', 'visit', 'admission');
    }

    private function createHospital(string $slug = 'test-hospital'): Hospital
    {
        $hospital = Hospital::create([
            'name' => 'Test Hospital',
            'slug' => $slug,
            'is_active' => true,
        ]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'inpatient',
            'enabled' => true,
        ]);

        return $hospital;
    }

    private function createDepartment(Hospital $hospital, string $name, bool $hasWard): Department
    {
        return Department::create([
            'hospital_id' => $hospital->id,
            'name' => $name,
            'has_ward' => $hasWard,
        ]);
    }

    private function createPatient(Hospital $hospital): Patient
    {
        return Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'HMS-'.fake()->unique()->numerify('#####'),
            'first_name' => 'Avery',
            'last_name' => 'Patient',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
        ]);
    }
}
