<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\MaternityEpisode;
use App\Models\MaternityNewborn;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaternityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_maternity_api_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/maternity/episodes')->assertUnauthorized();
    }

    public function test_doctor_can_start_delivery_and_postpartum_workflow_for_an_active_admission(): void
    {
        $case = $this->createInpatientCase();
        HospitalSetting::create([
            'hospital_id' => $case['hospital']->id,
            'name' => $case['hospital']->name,
            'maternity_vaginal_delivery_fee' => 12000,
            'maternity_delivery_pack_fee' => 1800,
        ]);
        Sanctum::actingAs($case['doctor']);

        $startResponse = $this->postJson("/api/v1/maternity/admissions/{$case['admission']->id}/episodes", [
            'labor_started_at' => now()->subHour()->toDateTimeString(),
            'labor_assessment' => 'Initial assessment.',
            'patient_brought_delivery_materials' => false,
        ]);

        $startResponse->assertCreated()
            ->assertJsonPath('data.status', 'in_labor')
            ->assertJsonPath('data.admission.id', $case['admission']->id);
        $episodeId = $startResponse->json('data.id');

        $deliveryResponse = $this->postJson("/api/v1/maternity/episodes/{$episodeId}/delivery", [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => true,
            'delivery_material_notes' => 'Pack supplied by the hospital.',
            'newborns' => [
                [
                    'outcome' => 'live_birth',
                    'first_name' => 'Baby',
                    'last_name' => 'Patient',
                    'gender' => 'female',
                ],
                ['outcome' => 'stillbirth'],
            ],
        ]);

        $deliveryResponse->assertOk()
            ->assertJsonPath('data.status', 'postpartum')
            ->assertJsonPath('data.patient_brought_delivery_materials', false)
            ->assertJsonPath('data.hospital_supplied_delivery_pack', true)
            ->assertJsonPath('data.delivery_material_notes', 'Pack supplied by the hospital.')
            ->assertJsonPath('data.newborns.0.outcome', 'live_birth')
            ->assertJsonPath('data.newborns.0.patient.name', 'Baby Patient')
            ->assertJsonPath('data.newborns.1.outcome', 'stillbirth')
            ->assertJsonPath('data.newborns.1.patient', null);

        $liveBirth = MaternityNewborn::query()
            ->where('maternity_episode_id', $episodeId)
            ->where('outcome', 'live_birth')
            ->sole();
        $stillbirth = MaternityNewborn::query()
            ->where('maternity_episode_id', $episodeId)
            ->where('outcome', 'stillbirth')
            ->sole();
        $this->assertNotNull($liveBirth->patient_id);
        $this->assertNull($stillbirth->patient_id);
        $this->assertDatabaseHas('patients', [
            'id' => $liveBirth->patient_id,
            'hospital_id' => $case['hospital']->id,
            'first_name' => 'Baby',
            'last_name' => 'Patient',
            'registration_paid_at' => null,
        ]);
        $invoice = Invoice::query()->where('visit_id', $case['admission']->visit_id)->sole();
        $this->assertSame(13800.0, (float) $invoice->total);
        $this->assertDatabaseCount('invoice_items', 2);

        $this->putJson("/api/v1/maternity/episodes/{$episodeId}/postpartum", [
            'postpartum_notes' => 'Mother and baby transferred for routine postpartum care.',
        ])
            ->assertOk()
            ->assertJsonPath('data.postpartum_notes', 'Mother and baby transferred for routine postpartum care.');
        $this->assertDatabaseHas('maternity_episodes', [
            'id' => $episodeId,
            'status' => 'postpartum',
            'postpartum_notes' => 'Mother and baby transferred for routine postpartum care.',
        ]);
    }

    public function test_delivery_fees_endpoint_returns_the_current_hospitals_configured_rates(): void
    {
        $case = $this->createInpatientCase();
        HospitalSetting::create([
            'hospital_id' => $case['hospital']->id,
            'name' => $case['hospital']->name,
            'maternity_vaginal_delivery_fee' => 14000,
            'maternity_assisted_delivery_fee' => 16500,
            'maternity_cesarean_delivery_fee' => 32000,
            'maternity_other_delivery_fee' => 19000,
            'maternity_delivery_pack_fee' => 2300,
        ]);
        Sanctum::actingAs($case['doctor']);

        $this->getJson('/api/v1/maternity/fees')
            ->assertOk()
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.delivery_fees.vaginal', 14000)
            ->assertJsonPath('data.delivery_fees.assisted_vaginal', 16500)
            ->assertJsonPath('data.delivery_fees.cesarean', 32000)
            ->assertJsonPath('data.delivery_fees.other', 19000)
            ->assertJsonPath('data.delivery_fees.delivery_pack', 2300);
    }

    public function test_labor_cannot_start_without_recording_whether_the_patient_brought_materials(): void
    {
        $case = $this->createInpatientCase();
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/admissions/{$case['admission']->id}/episodes", [
            'labor_started_at' => now()->subHour()->toDateTimeString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_brought_delivery_materials']);

        $this->assertDatabaseCount('maternity_episodes', 0);
    }

    public function test_delivery_cannot_be_recorded_without_confirming_whether_the_hospital_supplied_a_pack(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'newborns' => [[
                'outcome' => 'live_birth',
                'first_name' => 'Baby',
                'last_name' => 'Patient',
                'gender' => 'female',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hospital_supplied_delivery_pack']);

        $this->assertDatabaseCount('maternity_newborns', 0);
        $this->assertDatabaseMissing('invoices', [
            'visit_id' => $case['admission']->visit_id,
        ]);
    }

    public function test_returns_validation_errors_for_a_malformed_newborn_entry_without_creating_records(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => false,
            'newborns' => ['not-an-object'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['newborns.0']);

        $this->assertDatabaseMissing('maternity_newborns', [
            'maternity_episode_id' => $episode->id,
        ]);
    }

    public function test_returns_validation_errors_when_a_live_birth_is_missing_required_patient_details(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $response = $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => false,
            'newborns' => [['outcome' => 'live_birth']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'newborns.0.first_name',
                'newborns.0.last_name',
                'newborns.0.gender',
            ]);
        $this->assertSame(
            'A name and sex are required to create a patient record for a live-born baby.',
            $response->json('errors')['newborns.0.first_name'][0],
        );

        $this->assertDatabaseMissing('maternity_newborns', [
            'maternity_episode_id' => $episode->id,
        ]);
    }

    public function test_rejects_a_second_maternity_episode_for_the_same_admission(): void
    {
        $case = $this->createInpatientCase();
        $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/admissions/{$case['admission']->id}/episodes", [
            'patient_brought_delivery_materials' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A maternity episode already exists for this admission.');

        $this->assertDatabaseCount('maternity_episodes', 1);
    }

    public function test_returns_404_when_a_doctor_requests_an_episode_from_another_hospital(): void
    {
        $case = $this->createInpatientCase();
        $otherCase = $this->createInpatientCase('other-maternity-hospital');
        $otherEpisode = $this->createEpisode($otherCase);
        Sanctum::actingAs($case['doctor']);

        $this->getJson("/api/v1/maternity/episodes/{$otherEpisode->id}")->assertNotFound();
    }

    public function test_returns_403_when_a_receptionist_requests_maternity_episodes(): void
    {
        $case = $this->createInpatientCase();
        $receptionist = User::factory()->create([
            'hospital_id' => $case['hospital']->id,
            'department_id' => $case['ward']->id,
            'role' => Role::Receptionist,
            'is_active' => true,
        ]);
        Sanctum::actingAs($receptionist);

        $this->getJson('/api/v1/maternity/episodes')->assertForbidden();
    }

    public function test_returns_404_when_maternity_module_is_disabled(): void
    {
        $case = $this->createInpatientCase(maternityEnabled: false);
        Sanctum::actingAs($case['doctor']);

        $this->getJson('/api/v1/maternity/episodes')->assertNotFound();
    }

    /**
     * @return array{
     *     hospital: Hospital,
     *     ward: Department,
     *     doctor: User,
     *     patient: Patient,
     *     admission: Admission
     * }
     */
    private function createInpatientCase(
        string $slug = 'maternity-api-test-hospital',
        bool $maternityEnabled = true,
    ): array {
        $hospital = Hospital::create([
            'name' => 'Maternity API Test Hospital',
            'slug' => $slug,
            'is_active' => true,
        ]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'inpatient',
            'enabled' => true,
        ]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'maternity',
            'enabled' => $maternityEnabled,
        ]);

        $ward = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Maternity Ward',
            'has_ward' => true,
        ]);
        $doctor = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $ward->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        $patient = Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'MAT-'.fake()->unique()->numerify('#####'),
            'first_name' => 'Avery',
            'last_name' => 'Patient',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
        ]);
        $visit = Visit::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'consultation',
            'status' => VisitStatus::Inpatient,
            'checked_in_at' => now(),
        ]);
        $admission = Admission::create([
            'hospital_id' => $hospital->id,
            'admission_number' => 'ADM-MAT-'.fake()->unique()->numerify('#####'),
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $ward->id,
            'attending_doctor_id' => $doctor->id,
            'admitted_by' => $doctor->id,
            'status' => 'active',
            'admitted_at' => now(),
            'admission_reason' => 'Labor and delivery',
            'admission_diagnosis' => 'Labor',
            'initial_treatment_plan' => 'Maternity care',
        ]);

        return compact('hospital', 'ward', 'doctor', 'patient', 'admission');
    }

    /**
     * @param array{
     *     hospital: Hospital,
     *     ward: Department,
     *     doctor: User,
     *     patient: Patient,
     *     admission: Admission
     * } $case
     */
    private function createEpisode(array $case): MaternityEpisode
    {
        return MaternityEpisode::create([
            'hospital_id' => $case['hospital']->id,
            'admission_id' => $case['admission']->id,
            'created_by' => $case['doctor']->id,
            'status' => 'in_labor',
            'labor_started_at' => now()->subHour(),
            'patient_brought_delivery_materials' => true,
        ]);
    }
}
