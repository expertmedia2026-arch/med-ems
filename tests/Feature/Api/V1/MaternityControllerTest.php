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
use App\Services\MaternityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaternityControllerTest extends TestCase
{
    use RefreshDatabase;

    /** The API must require an authenticated caller before exposing maternity data. */
    public function test_returns_401_when_maternity_api_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/maternity/episodes')->assertUnauthorized();
    }

    /** The API persists labor, live and stillbirth outcomes, fees, and postpartum notes. */
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
                    'birth_weight_grams' => 3250,
                    'apgar_score_1_minute' => 8,
                    'apgar_score_5_minutes' => 9,
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
            ->assertJsonPath('data.newborns.0.birth_weight_grams', 3250)
            ->assertJsonPath('data.newborns.0.apgar_score_1_minute', 8)
            ->assertJsonPath('data.newborns.0.apgar_score_5_minutes', 9)
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
        $this->assertDatabaseHas('maternity_newborns', [
            'id' => $liveBirth->id,
            'birth_weight_grams' => 3250,
            'apgar_score_1_minute' => 8,
            'apgar_score_5_minutes' => 9,
        ]);
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

    /** Fee lookup must resolve prices from the authenticated hospital's settings. */
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

    /** The required materials checklist prevents an incomplete labor record. */
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

    /** Invalid checklist text must not be silently converted to a negative answer. */
    public function test_returns_validation_error_for_an_invalid_patient_materials_answer(): void
    {
        $case = $this->createInpatientCase();
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/admissions/{$case['admission']->id}/episodes", [
            'patient_brought_delivery_materials' => 'not-recorded',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_brought_delivery_materials']);

        $this->assertDatabaseCount('maternity_episodes', 0);
    }

    /** Delivery cannot be submitted until staff confirms whether a hospital pack was supplied. */
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

    /** Invalid pack answers must not be silently converted to a negative answer. */
    public function test_returns_validation_error_for_an_invalid_hospital_pack_answer(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => 'not-recorded',
            'newborns' => [[
                'outcome' => 'stillbirth',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hospital_supplied_delivery_pack']);

        $this->assertDatabaseCount('maternity_newborns', 0);
    }

    /** Delivery observations must stay within their clinical numeric ranges. */
    public function test_returns_validation_errors_for_invalid_newborn_delivery_observations(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => false,
            'newborns' => [[
                'outcome' => 'stillbirth',
                'birth_weight_grams' => 15001,
                'apgar_score_1_minute' => -1,
                'apgar_score_5_minutes' => 11,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'newborns.0.birth_weight_grams',
                'newborns.0.apgar_score_1_minute',
                'newborns.0.apgar_score_5_minutes',
            ]);

        $this->assertDatabaseCount('maternity_newborns', 0);
    }

    /** Malformed newborn rows return validation errors without partially creating birth records. */
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

    /** A live birth needs a name and gender before its linked patient can be created. */
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

    /** Admission uniqueness prevents duplicate labor episodes even through repeated API calls. */
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

    /** Delivery timestamps cannot precede the documented start of labor. */
    public function test_returns_422_when_delivery_time_is_before_labor_started(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [
            'delivery_at' => now()->subHours(2)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => false,
            'newborns' => [['outcome' => 'stillbirth']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_at']);

        $this->assertDatabaseCount('maternity_newborns', 0);
        $this->assertDatabaseMissing('maternity_episodes', [
            'id' => $episode->id,
            'status' => 'postpartum',
        ]);
    }

    /** Repeated delivery submissions cannot duplicate newborns or billing. */
    public function test_returns_422_without_duplicate_records_when_delivery_is_submitted_twice(): void
    {
        $case = $this->createInpatientCase();
        HospitalSetting::create([
            'hospital_id' => $case['hospital']->id,
            'name' => $case['hospital']->name,
            'maternity_vaginal_delivery_fee' => 12000,
        ]);
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);
        $payload = [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => false,
            'newborns' => [['outcome' => 'stillbirth']],
        ];
        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", $payload)->assertOk();

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Delivery details can only be recorded for an episode in labor.');

        $this->assertDatabaseCount('maternity_newborns', 1);
        $this->assertDatabaseCount('invoice_items', 1);
    }

    /** Postpartum notes are rejected until delivery moves the episode to postpartum. */
    public function test_returns_422_when_postpartum_notes_are_recorded_before_delivery(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        Sanctum::actingAs($case['doctor']);

        $this->putJson("/api/v1/maternity/episodes/{$episode->id}/postpartum", [
            'postpartum_notes' => 'Entered too early.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Postpartum notes can only be recorded after delivery.');

        $this->assertDatabaseMissing('maternity_episodes', [
            'id' => $episode->id,
            'postpartum_notes' => 'Entered too early.',
        ]);
    }

    /** Hospital isolation hides an episode that belongs to a different tenant. */
    public function test_returns_404_when_a_doctor_requests_an_episode_from_another_hospital(): void
    {
        $case = $this->createInpatientCase();
        $otherCase = $this->createInpatientCase('other-maternity-hospital');
        $otherEpisode = $this->createEpisode($otherCase);
        Sanctum::actingAs($case['doctor']);

        $this->getJson("/api/v1/maternity/episodes/{$otherEpisode->id}")->assertNotFound();
    }

    /** The shared access check rejects cross-hospital records even outside route binding. */
    public function test_shared_access_check_rejects_an_admin_from_another_hospital(): void
    {
        $case = $this->createInpatientCase();
        $otherCase = $this->createInpatientCase('isolated-maternity-hospital');
        $admin = User::factory()->create([
            'hospital_id' => $case['hospital']->id,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        $canAccess = app(MaternityService::class)->canAccessAdmission($otherCase['admission'], $admin);

        $this->assertFalse($canAccess);
    }

    /** Same-hospital clinicians outside the ward cannot read the maternity episode. */
    public function test_returns_403_when_a_clinician_from_another_ward_requests_an_episode(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        $otherWard = Department::create([
            'hospital_id' => $case['hospital']->id,
            'name' => 'Surgical Ward',
            'has_ward' => true,
        ]);
        $otherWardNurse = User::factory()->create([
            'hospital_id' => $case['hospital']->id,
            'department_id' => $otherWard->id,
            'role' => Role::Nurse,
            'is_active' => true,
        ]);
        Sanctum::actingAs($otherWardNurse);

        $this->getJson("/api/v1/maternity/episodes/{$episode->id}")->assertForbidden();
    }

    /** Record authorization runs before validation for labor-start writes. */
    public function test_returns_403_before_validation_when_another_ward_starts_labor(): void
    {
        $case = $this->createInpatientCase();
        $otherWard = Department::create([
            'hospital_id' => $case['hospital']->id,
            'name' => 'Medical Ward',
            'has_ward' => true,
        ]);
        $otherWardDoctor = User::factory()->create([
            'hospital_id' => $case['hospital']->id,
            'department_id' => $otherWard->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        Sanctum::actingAs($otherWardDoctor);

        $this->postJson("/api/v1/maternity/admissions/{$case['admission']->id}/episodes", [])
            ->assertForbidden();

        $this->assertDatabaseCount('maternity_episodes', 0);
    }

    /** Record authorization runs before validation for delivery writes. */
    public function test_returns_403_before_validation_when_another_ward_records_delivery(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        $otherWard = Department::create([
            'hospital_id' => $case['hospital']->id,
            'name' => 'Emergency Ward',
            'has_ward' => true,
        ]);
        $otherWardNurse = User::factory()->create([
            'hospital_id' => $case['hospital']->id,
            'department_id' => $otherWard->id,
            'role' => Role::Nurse,
            'is_active' => true,
        ]);
        Sanctum::actingAs($otherWardNurse);

        $this->postJson("/api/v1/maternity/episodes/{$episode->id}/delivery", [])
            ->assertForbidden();

        $this->assertDatabaseCount('maternity_newborns', 0);
    }

    /** Record authorization runs before validation for postpartum writes. */
    public function test_returns_403_before_validation_when_another_ward_updates_postpartum_notes(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);
        $otherWard = Department::create([
            'hospital_id' => $case['hospital']->id,
            'name' => 'Paediatric Ward',
            'has_ward' => true,
        ]);
        $otherWardDoctor = User::factory()->create([
            'hospital_id' => $case['hospital']->id,
            'department_id' => $otherWard->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        Sanctum::actingAs($otherWardDoctor);

        $this->putJson("/api/v1/maternity/episodes/{$episode->id}/postpartum", [])
            ->assertForbidden();

        $this->assertNull($episode->fresh()->postpartum_notes);
    }

    /** Only the clinical roles allowed by the maternity route may read the workflow. */
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

    /** The module middleware makes API endpoints unavailable when a tenant disables maternity. */
    public function test_returns_404_when_maternity_module_is_disabled(): void
    {
        $case = $this->createInpatientCase(maternityEnabled: false);
        Sanctum::actingAs($case['doctor']);

        $this->getJson('/api/v1/maternity/episodes')->assertNotFound();
    }

    /**
     * Create the tenant-scoped active admission and clinician used by API tests.
     *
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
     * Create a labor-stage episode for endpoint tests that begin with delivery or access checks.
     *
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
