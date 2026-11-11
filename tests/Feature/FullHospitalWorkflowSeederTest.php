<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FullHospitalWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_seeds_a_repeat_safe_complete_workflow_for_only_the_selected_hospital(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $untouchedHospital = Hospital::create([
            'name' => 'Untouched Hospital',
            'slug' => 'untouched-hospital',
            'is_active' => true,
        ]);
        $hospital = Hospital::create([
            'name' => 'Eastern Hope Hospital',
            'slug' => 'eastern-hope-hospital',
            'is_active' => true,
        ]);

        $this->artisan('hospital:seed-full-demo', ['--hospital' => $hospital->id])->assertSuccessful();

        $this->assertSame(40, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count());
        $this->assertSame(24, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('is_nhis', true)->count());
        $this->assertSame(16, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('is_nhis', false)->count());
        $this->assertSame(40, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->whereLike('hospital_number', 'EHH-26-%')->count());
        $this->assertSame(12, $this->tenantTableCount('departments', $hospital->id));
        $this->assertSame(16, $this->tenantTableCount('consultation_types', $hospital->id));
        $this->assertSame(16, $this->tenantTableCount('appointments', $hospital->id));
        $this->assertSame(32, $this->tenantTableCount('visits', $hospital->id));
        $this->assertSame(5, $this->tenantTableCount('admissions', $hospital->id));
        $this->assertSame(5, $this->tenantTableCount('admission_vitals', $hospital->id));
        $this->assertSame(10, $this->tenantTableCount('admission_notes', $hospital->id));
        $this->assertSame(72, $this->tenantTableCount('invoices', $hospital->id));
        $this->assertSame(3, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('type', 'lab_only')->whereNotNull('referred_from')->count());
        $this->assertSame(3, $this->tenantTableCount('admissions', $hospital->id, 'status', 'active'));
        $this->assertSame(2, $this->tenantTableCount('admissions', $hospital->id, 'status', 'discharged'));
        $this->assertGreaterThanOrEqual(5, $this->tenantTableCount('medication_administrations', $hospital->id));
        $this->assertGreaterThan(0, $this->tenantTableCount('lab_requests', $hospital->id, 'status', 'completed'));
        $this->assertGreaterThan(0, $this->tenantTableCount('lab_requests', $hospital->id, 'status', 'pending'));
        $this->assertGreaterThan(0, $this->tenantTableCount('prescriptions', $hospital->id, 'status', 'completed'));
        $this->assertGreaterThan(0, $this->tenantTableCount('prescriptions', $hospital->id, 'status', 'pending'));
        $this->assertGreaterThan(0, Invoice::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('status', 'paid')->count());
        $this->assertGreaterThan(0, Invoice::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('status', 'partial')->count());
        $this->assertGreaterThan(0, Invoice::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('status', 'unpaid')->count());
        $this->assertSame(0, Patient::withoutGlobalScopes()->where('hospital_id', $untouchedHospital->id)->count());

        $patients = Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->orderBy('id')->get();
        $this->assertSame(2, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('patient_id', $patients[0]->id)->count());
        $this->assertSame(0, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('patient_id', $patients[39]->id)->count());

        $visitCount = Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count();
        $paymentCount = $this->tenantTableCount('payments', $hospital->id);
        $stockQuantity = Drug::withoutGlobalScopes()->where('hospital_id', $hospital->id)->sum('stock_quantity');

        $this->artisan('hospital:seed-full-demo', ['--hospital' => $hospital->id])->assertSuccessful();

        $this->assertSame($visitCount, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count());
        $this->assertSame($paymentCount, $this->tenantTableCount('payments', $hospital->id));
        $this->assertSame($stockQuantity, Drug::withoutGlobalScopes()->where('hospital_id', $hospital->id)->sum('stock_quantity'));
    }

    public function test_command_requires_a_positive_hospital_id(): void
    {
        $this->artisan('hospital:seed-full-demo')
            ->expectsOutputToContain('Provide a positive hospital ID')
            ->assertFailed();
    }

    private function tenantTableCount(string $table, int $hospitalId, ?string $column = null, mixed $value = null): int
    {
        $query = DB::table($table)->where('hospital_id', $hospitalId);

        if ($column !== null) {
            $query->where($column, $value);
        }

        return $query->count();
    }
}
