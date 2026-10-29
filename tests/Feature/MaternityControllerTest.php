<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class MaternityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_maternity_menu_is_shown_when_the_hospital_has_the_module_enabled(): void
    {
        $case = $this->createInpatientCase();

        $this->actingAs($case['doctor'])
            ->get(route('maternity.episodes.index'))
            ->assertOk()
            ->assertSee('Maternity')
            ->assertSee(route('maternity.episodes.index'));
    }

    public function test_doctor_can_start_a_maternity_episode_from_an_active_admission(): void
    {
        $case = $this->createInpatientCase();
        HospitalSetting::create([
            'hospital_id' => $case['hospital']->id,
            'name' => $case['hospital']->name,
            'maternity_vaginal_delivery_fee' => 18000,
            'maternity_delivery_pack_fee' => 2500,
        ]);
        $laborStartedAt = now()->subHour()->format('Y-m-d\TH:i');

        $response = $this->actingAs($case['doctor'])->post(route('maternity.episodes.start', $case['admission']), [
            'labor_started_at' => $laborStartedAt,
            'labor_assessment' => 'Initial assessment.',
            'patient_brought_delivery_materials' => '0',
        ]);

        $episode = MaternityEpisode::query()->sole();

        $response->assertRedirect(route('maternity.episodes.show', $episode));
        $this->assertDatabaseHas('maternity_episodes', [
            'id' => $episode->id,
            'admission_id' => $case['admission']->id,
            'status' => 'in_labor',
            'labor_assessment' => 'Initial assessment.',
        ]);
        $this->get(route('maternity.episodes.show', $episode))
            ->assertOk()
            ->assertSee('Record delivery')
            ->assertSee('Patient brought delivery materials')
            ->assertSee('₦18,000.00');
    }

    public function test_doctor_can_record_delivery_and_postpartum_notes_in_the_web_workflow(): void
    {
        $case = $this->createInpatientCase();
        HospitalSetting::create([
            'hospital_id' => $case['hospital']->id,
            'name' => $case['hospital']->name,
            'maternity_vaginal_delivery_fee' => 18000,
            'maternity_delivery_pack_fee' => 2500,
        ]);
        $episode = $this->createEpisode($case);

        $deliveryResponse = $this->actingAs($case['doctor'])->post(route('maternity.episodes.delivery', $episode), [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => '1',
            'delivery_material_notes' => 'Hospital pack supplied.',
            'newborns' => [[
                'outcome' => 'live_birth',
                'first_name' => 'Baby',
                'last_name' => 'Patient',
                'gender' => 'female',
            ]],
        ]);

        $deliveryResponse->assertRedirect(route('maternity.episodes.show', $episode));
        $this->assertDatabaseHas('maternity_episodes', [
            'id' => $episode->id,
            'status' => 'postpartum',
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => true,
            'delivery_material_notes' => 'Hospital pack supplied.',
        ]);
        $this->assertDatabaseHas('maternity_newborns', [
            'maternity_episode_id' => $episode->id,
            'outcome' => 'live_birth',
            'first_name' => 'Baby',
        ]);

        $newborn = MaternityNewborn::query()->where('maternity_episode_id', $episode->id)->sole();
        $this->assertNotNull($newborn->patient_id);
        $this->assertDatabaseHas('patients', [
            'id' => $newborn->patient_id,
            'first_name' => 'Baby',
            'last_name' => 'Patient',
        ]);
        $invoice = Invoice::query()->where('visit_id', $case['admission']->visit_id)->sole();
        $this->assertSame(20500.0, (float) $invoice->total);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'category' => 'maternity',
            'description' => 'Vaginal delivery fee',
            'unit_price' => 18000,
            'billable_type' => MaternityEpisode::class,
            'billable_id' => $episode->id,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'category' => 'maternity',
            'description' => 'Hospital-supplied delivery pack',
            'unit_price' => 2500,
            'billable_type' => MaternityEpisode::class,
            'billable_id' => $episode->id,
        ]);

        $this->put(route('maternity.episodes.postpartum', $episode), [
            'postpartum_notes' => 'Mother and baby transferred for routine postpartum care.',
        ])->assertRedirect(route('maternity.episodes.show', $episode));
        $this->assertDatabaseHas('maternity_episodes', [
            'id' => $episode->id,
            'postpartum_notes' => 'Mother and baby transferred for routine postpartum care.',
        ]);
    }

    public function test_delivery_pack_fee_is_not_billed_when_the_hospital_did_not_supply_a_pack(): void
    {
        $case = $this->createInpatientCase();
        HospitalSetting::create([
            'hospital_id' => $case['hospital']->id,
            'name' => $case['hospital']->name,
            'maternity_cesarean_delivery_fee' => 30000,
            'maternity_delivery_pack_fee' => 7000,
        ]);
        $episode = $this->createEpisode($case);

        $this->actingAs($case['doctor'])->post(route('maternity.episodes.delivery', $episode), [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'cesarean',
            'hospital_supplied_delivery_pack' => '0',
            'newborns' => [[
                'outcome' => 'live_birth',
                'first_name' => 'Baby',
                'last_name' => 'Patient',
                'gender' => 'female',
            ]],
        ])->assertRedirect(route('maternity.episodes.show', $episode));

        $invoice = Invoice::query()->where('visit_id', $case['admission']->visit_id)->sole();
        $this->assertSame(30000.0, (float) $invoice->total);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Cesarean delivery fee',
            'unit_price' => 30000,
        ]);
    }

    public function test_zero_delivery_fees_do_not_create_an_empty_invoice(): void
    {
        $case = $this->createInpatientCase();
        $episode = $this->createEpisode($case);

        $this->actingAs($case['doctor'])->post(route('maternity.episodes.delivery', $episode), [
            'delivery_at' => now()->subMinutes(30)->toDateTimeString(),
            'delivery_mode' => 'vaginal',
            'hospital_supplied_delivery_pack' => '0',
            'newborns' => [[
                'outcome' => 'live_birth',
                'first_name' => 'Baby',
                'last_name' => 'Patient',
                'gender' => 'female',
            ]],
        ])->assertRedirect(route('maternity.episodes.show', $episode));

        $this->assertDatabaseMissing('invoices', [
            'visit_id' => $case['admission']->visit_id,
        ]);
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
    private function createInpatientCase(bool $maternityEnabled = true): array
    {
        $hospital = Hospital::create([
            'name' => 'Maternity Test Hospital',
            'slug' => 'maternity-test-hospital',
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
