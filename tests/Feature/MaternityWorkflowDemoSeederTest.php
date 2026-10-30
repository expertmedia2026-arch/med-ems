<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Admission;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\Invoice;
use App\Models\MaternityEpisode;
use App\Models\MaternityNewborn;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MaternityWorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MaternityWorkflowDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The demo cohort covers varied clinical histories and complete billing states once. */
    public function test_seeds_ten_professional_maternity_cases_with_billing_and_is_safe_to_rerun(): void
    {
        $hospital = $this->createHospitalWithAntenatalConsultation();

        $this->runSeederFor($hospital);
        $this->runSeederFor($hospital);

        $this->assertDatabaseCount('maternity_episodes', 10);
        $this->assertDatabaseCount('maternity_newborns', 11);
        $this->assertDatabaseCount('patients', 19);
        $this->assertDatabaseCount('admissions', 10);
        $this->assertDatabaseCount('invoices', 65);
        $this->assertDatabaseCount('payments', 49);

        $marker = '[MATERNITY_DEMO_HOSPITAL_'.$hospital->id.']';
        $demoVisits = Visit::query()->where('clinical_notes', 'like', '%'.$marker.'%');
        $this->assertSame(56, $demoVisits->count());
        $this->assertSame(
            46,
            $demoVisits->whereNotNull('consultation_type_id')
                ->where('status', 'completed')
                ->count(),
        );

        $this->assertSame(9, MaternityEpisode::query()->where('status', 'postpartum')->count());
        $this->assertSame(1, MaternityEpisode::query()->where('status', 'in_labor')->count());
        $this->assertSame(9, MaternityNewborn::query()->whereNotNull('patient_id')->count());
        $this->assertSame(2, MaternityNewborn::query()->where('outcome', 'stillbirth')->count());
        $this->assertSame(11, MaternityNewborn::query()->whereNotNull('birth_weight_grams')->count());
        $this->assertSame(3, Admission::query()->where('status', 'active')->count());
        $this->assertSame(7, Admission::query()->where('status', 'discharged')->count());
        $this->assertSame(46, Invoice::query()->where('status', 'paid')->count());
        $this->assertSame(3, Invoice::query()->where('status', 'partial')->count());
        $this->assertSame(16, Invoice::query()->where('status', 'unpaid')->count());
        $this->assertSame(49, Payment::query()->count());
        $this->assertSame(10, Patient::query()->whereNotNull('registration_paid_at')->whereLike('hospital_number', 'MAT-DEMO-%')->count());
        $this->assertSame(1, Patient::query()->where('hospital_number', 'MAT-DEMO-'.$hospital->id.'-02')->count());
        $this->assertSame(1, $hospital->modules()->where('module', 'maternity')->where('enabled', true)->count());
    }

    /** The custom command applies the cohort only to the explicitly selected hospital. */
    public function test_command_targets_the_requested_hospital_without_cross_tenant_records(): void
    {
        $untouchedHospital = $this->createHospitalWithAntenatalConsultation(slug: 'untouched-maternity-hospital');
        $targetHospital = $this->createHospitalWithAntenatalConsultation(slug: 'target-maternity-hospital');

        $this->artisan('maternity:seed-demo', ['--hospital' => $targetHospital->id])
            ->expectsOutputToContain("Seeded 10 professional maternity workflow cases for hospital ID {$targetHospital->id}.")
            ->assertSuccessful();

        $this->assertSame(0, MaternityEpisode::withoutGlobalScopes()->where('hospital_id', $untouchedHospital->id)->count());
        $this->assertSame(10, MaternityEpisode::withoutGlobalScopes()->where('hospital_id', $targetHospital->id)->count());
    }

    /** Refresh replaces only the marked cohort and preserves genuine hospital patients. */
    public function test_command_refresh_rebuilds_demo_records_without_touching_other_patients(): void
    {
        $hospital = $this->createHospitalWithAntenatalConsultation(slug: 'refresh-maternity-hospital');
        $genuinePatient = Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'REAL-001',
            'first_name' => 'Genuine',
            'last_name' => 'Patient',
            'gender' => 'female',
            'date_of_birth' => '1992-04-12',
        ]);
        $this->runSeederFor($hospital);
        $oldEpisodeIds = MaternityEpisode::query()->pluck('id');

        $this->artisan('maternity:seed-demo', [
            '--hospital' => $hospital->id,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertModelExists($genuinePatient);
        $this->assertSame(10, MaternityEpisode::query()->count());
        $this->assertSame(0, MaternityEpisode::query()->whereIn('id', $oldEpisodeIds)->count());
        $this->assertSame(19, Patient::query()->where('hospital_id', $hospital->id)->where('hospital_number', '!=', 'REAL-001')->count());
        $this->assertSame(65, Invoice::query()->count());
    }

    /** A hospital ID is mandatory so the command cannot seed an accidental tenant. */
    public function test_command_fails_without_a_valid_hospital_id(): void
    {
        $this->artisan('maternity:seed-demo')
            ->expectsOutputToContain('Provide a positive hospital ID')
            ->assertFailed();
    }

    /** Seeding must stop before writes when required tenant modules are disabled. */
    public function test_refuses_to_seed_when_the_hospital_has_not_enabled_maternity(): void
    {
        $hospital = $this->createHospitalWithAntenatalConsultation(maternityEnabled: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Enable both Inpatient and Maternity for hospital ID {$hospital->id}");

        $this->runSeederFor($hospital);
    }

    /** Build the enabled-module and antenatal consultation prerequisites for seeding. */
    private function createHospitalWithAntenatalConsultation(
        bool $maternityEnabled = true,
        string $slug = 'maternity-demo-seed-hospital',
    ): Hospital {
        $hospital = Hospital::create([
            'name' => 'Maternity Demo Seed Hospital',
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
        $department = Department::create([
            'hospital_id' => $hospital->id,
            'name' => 'Maternity Ward',
            'has_ward' => true,
        ]);
        ConsultationType::create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'name' => 'Antenatal Consultation',
            'description' => 'Antenatal assessment and pregnancy review.',
            'price' => 6000,
            'is_active' => true,
        ]);
        User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'role' => Role::Doctor,
            'is_active' => true,
        ]);
        User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        return $hospital;
    }

    /** Invoke the seeder through Laravel's container so method dependencies are injected. */
    private function runSeederFor(Hospital $hospital): void
    {
        $seeder = app(MaternityWorkflowDemoSeeder::class);
        $seeder->hospitalId = $hospital->id;
        $seeder->setContainer(app());
        $seeder();
    }
}
