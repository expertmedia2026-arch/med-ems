<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\MaternityEpisode;
use App\Models\MaternityNewborn;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MaternityWorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MaternityWorkflowDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** The demo seeder creates ten linked cases once and preserves antenatal visit history. */
    public function test_seeds_ten_maternity_cases_with_antenatal_history_and_is_safe_to_rerun(): void
    {
        $hospital = $this->createHospitalWithAntenatalConsultation();
        $seeder = new MaternityWorkflowDemoSeeder;
        $seeder->hospitalId = $hospital->id;

        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('maternity_episodes', 10);
        $this->assertDatabaseCount('maternity_newborns', 10);
        $this->assertDatabaseCount('patients', 20);
        $this->assertDatabaseCount('admissions', 10);

        $marker = '[MATERNITY_DEMO_HOSPITAL_'.$hospital->id.']';
        $demoVisits = Visit::query()->where('clinical_notes', 'like', '%'.$marker.'%');
        $this->assertSame(30, $demoVisits->count());
        $this->assertSame(
            20,
            $demoVisits->whereNotNull('consultation_type_id')
                ->where('status', 'completed')
                ->count(),
        );

        $this->assertSame(9, MaternityEpisode::query()->where('status', 'postpartum')->count());
        $this->assertSame(1, MaternityEpisode::query()->where('status', 'in_labor')->count());
        $this->assertSame(10, MaternityNewborn::query()->whereNotNull('patient_id')->count());
        $this->assertSame(1, $hospital->modules()->where('module', 'maternity')->where('enabled', true)->count());
    }

    /** Seeding must stop before writes when required tenant modules are disabled. */
    public function test_refuses_to_seed_when_the_hospital_has_not_enabled_maternity(): void
    {
        $hospital = $this->createHospitalWithAntenatalConsultation(maternityEnabled: false);
        $seeder = new MaternityWorkflowDemoSeeder;
        $seeder->hospitalId = $hospital->id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Enable both Inpatient and Maternity for hospital ID {$hospital->id}");

        $seeder->run();
    }

    /** Build the enabled-module and antenatal consultation prerequisites for seeding. */
    private function createHospitalWithAntenatalConsultation(bool $maternityEnabled = true): Hospital
    {
        $hospital = Hospital::create([
            'name' => 'Maternity Demo Seed Hospital',
            'slug' => 'maternity-demo-seed-hospital',
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

        return $hospital;
    }
}
