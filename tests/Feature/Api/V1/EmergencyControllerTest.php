<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_emergency_api_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/emergency')->assertUnauthorized();
    }

    public function test_forbids_a_nurse_without_emergency_access_from_viewing_the_queue(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Outpatient');
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);
        Sanctum::actingAs($nurse);

        $this->getJson('/api/v1/emergency')->assertForbidden();
    }

    public function test_doctor_cannot_register_a_patient_arrival(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Accident & Emergency');
        $doctor = $this->createStaff($hospital, $department, Role::Doctor);
        $patient = $this->createPatient($hospital);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/v1/emergency/patients/{$patient->id}", [
            'complaint' => 'Chest pain',
            'arrival_mode' => 'walk_in',
        ])->assertForbidden();

        $this->assertDatabaseCount('visits', 0);
    }

    public function test_receptionist_can_check_in_a_patient_once_and_receive_conflict_for_duplicate_arrivals(): void
    {
        $hospital = $this->createHospital();
        $receptionist = $this->createStaff($hospital, null, Role::Receptionist);
        $patient = $this->createPatient($hospital);
        Sanctum::actingAs($receptionist);
        $payload = ['complaint' => 'Shortness of breath', 'arrival_mode' => 'ambulance'];

        $response = $this->postJson("/api/v1/emergency/patients/{$patient->id}", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.is_emergency', true)
            ->assertJsonPath('data.status', VisitStatus::WaitingTriage->value);
        $this->assertDatabaseHas('visits', [
            'patient_id' => $patient->id,
            'is_emergency' => true,
            'arrival_mode' => 'ambulance',
            'status' => VisitStatus::WaitingTriage->value,
        ]);

        $this->postJson("/api/v1/emergency/patients/{$patient->id}", $payload)->assertConflict();
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_nurse_can_register_an_unidentified_emergency_arrival(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Accident & Emergency');
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);
        Sanctum::actingAs($nurse);

        $this->postJson('/api/v1/emergency/unidentified', [
            'complaint' => 'Unresponsive arrival',
            'arrival_mode' => 'ambulance',
            'gender' => 'unknown',
            'estimated_age' => 40,
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient.identity_status', 'unidentified')
            ->assertJsonPath('data.status', VisitStatus::WaitingTriage->value);

        $this->assertDatabaseHas('patients', [
            'identity_status' => 'unidentified',
            'first_name' => 'Unknown',
            'last_name' => 'A&E Arrival',
        ]);
    }

    public function test_emergency_queue_returns_active_visits_with_their_clinical_notes(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Accident & Emergency');
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);
        $patient = $this->createPatient($hospital);
        $visit = $this->createEmergencyVisit($hospital, $patient);
        $visit->emergencyProgressNotes()->create([
            'hospital_id' => $hospital->id,
            'author_id' => $nurse->id,
            'note_type' => 'nursing',
            'note' => 'Patient is receiving oxygen.',
        ]);
        Sanctum::actingAs($nurse);

        $this->getJson('/api/v1/emergency')
            ->assertOk()
            ->assertJsonPath('data.visits.data.0.id', $visit->id)
            ->assertJsonPath('data.visits.data.0.emergency_progress_notes.0.note', 'Patient is receiving oxygen.')
            ->assertJsonPath('data.visits.current_page', 1);
    }

    public function test_nurse_can_add_an_emergency_progress_note_to_an_active_visit(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Accident & Emergency');
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);
        $visit = $this->createEmergencyVisit($hospital, $this->createPatient($hospital));
        Sanctum::actingAs($nurse);

        $this->postJson("/api/v1/emergency/visits/{$visit->id}/notes", [
            'note' => 'Patient is stable after initial treatment.',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'nursing')
            ->assertJsonPath('data.author', $nurse->name);

        $this->assertDatabaseHas('emergency_progress_notes', [
            'visit_id' => $visit->id,
            'author_id' => $nurse->id,
            'note_type' => 'nursing',
            'note' => 'Patient is stable after initial treatment.',
        ]);
    }

    public function test_emergency_notes_cannot_be_read_or_written_across_hospitals(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Accident & Emergency');
        $nurse = $this->createStaff($hospital, $department, Role::Nurse);

        $otherHospital = $this->createHospital('other-emergency-hospital');
        $this->createDepartment($otherHospital, 'Accident & Emergency');
        $visit = $this->createEmergencyVisit($otherHospital, $this->createPatient($otherHospital));
        Sanctum::actingAs($nurse);

        $this->postJson("/api/v1/emergency/visits/{$visit->id}/notes", [
            'note' => 'Attempted cross-hospital note.',
        ])->assertNotFound();

        $this->assertDatabaseCount('emergency_progress_notes', 0);
    }

    public function test_a_and_e_doctor_can_confirm_a_brought_in_dead_arrival_and_approve_mortuary_transfer(): void
    {
        $hospital = $this->createHospital();
        $department = $this->createDepartment($hospital, 'Accident & Emergency');
        $doctor = $this->createStaff($hospital, $department, Role::Doctor);
        $patient = $this->createPatient($hospital);
        $visit = $this->createEmergencyVisit($hospital, $patient, broughtInDead: true, status: VisitStatus::WaitingDoctor);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/v1/emergency/visits/{$visit->id}/brought-in-dead/confirm", [
            'cause_of_death' => 'Severe head injury',
            'death_clinical_summary' => 'The patient arrived with no signs of life.',
            'disposition' => 'mortuary_transfer',
            'approval_confirmed' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_transfer')
            ->assertJsonPath('data.release_route', 'mortuary_transfer');

        $this->assertDatabaseHas('mortuary_cases', [
            'visit_id' => $visit->id,
            'source' => 'hospital',
            'status' => 'awaiting_transfer',
            'mortuary_transfer_approved_by' => $doctor->id,
        ]);
        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => VisitStatus::Completed->value,
            'disposition' => 'deceased',
        ]);
    }

    private function createHospital(string $slug = 'emergency-test-hospital'): Hospital
    {
        $hospital = Hospital::create([
            'name' => 'Emergency Test Hospital',
            'slug' => $slug,
            'is_active' => true,
        ]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'emergency',
            'enabled' => true,
        ]);

        return $hospital;
    }

    private function createDepartment(Hospital $hospital, string $name): Department
    {
        return Department::create([
            'hospital_id' => $hospital->id,
            'name' => $name,
        ]);
    }

    private function createStaff(Hospital $hospital, ?Department $department, Role $role): User
    {
        return User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department?->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createPatient(Hospital $hospital): Patient
    {
        return Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'A&E-'.fake()->unique()->numerify('#####'),
            'first_name' => 'Avery',
            'last_name' => 'Patient',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
        ]);
    }

    private function createEmergencyVisit(
        Hospital $hospital,
        Patient $patient,
        bool $broughtInDead = false,
        VisitStatus $status = VisitStatus::WaitingTriage,
    ): Visit {
        $department = Department::query()
            ->where('hospital_id', $hospital->id)
            ->where('name', 'Accident & Emergency')
            ->firstOrFail();

        return Visit::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'type' => 'consultation',
            'is_emergency' => true,
            'is_brought_in_dead' => $broughtInDead,
            'arrival_mode' => 'ambulance',
            'status' => $status,
            'checked_in_at' => now(),
            'department_id' => $department->id,
            'complaint' => 'Emergency test complaint',
        ]);
    }
}
