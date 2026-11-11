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

class UrologyCentreWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_seeds_a_repeat_safe_specialist_urology_hospital(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $untouchedHospital = Hospital::create([
            'name' => 'Untouched Specialist Hospital',
            'slug' => 'untouched-specialist-hospital',
            'is_active' => true,
        ]);
        $hospital = Hospital::create([
            'name' => 'UROLOGY CENTRE',
            'slug' => 'urology-centre',
            'is_active' => true,
        ]);

        $this->artisan('hospital:seed-urology-demo', ['--hospital' => $hospital->id])->assertSuccessful();

        $this->assertSame(30, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count());
        $this->assertSame(18, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('is_nhis', true)->count());
        $this->assertSame(12, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('is_nhis', false)->count());
        $this->assertSame(30, Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->whereLike('hospital_number', 'UC-26-%')->count());
        $this->assertSame(8, $this->tenantTableCount('departments', $hospital->id));
        $this->assertSame(25, $this->tenantTableCount('users', $hospital->id));
        $this->assertSame(5, $this->tenantTableCount('consultation_types', $hospital->id));
        $this->assertSame(12, $this->tenantTableCount('lab_tests', $hospital->id));
        $this->assertSame(12, $this->tenantTableCount('drugs', $hospital->id));
        $this->assertSame(14, $this->tenantTableCount('appointments', $hospital->id));
        $this->assertSame(23, $this->tenantTableCount('visits', $hospital->id));
        $this->assertSame(4, $this->tenantTableCount('admissions', $hospital->id));
        $this->assertSame(3, $this->tenantTableCount('admissions', $hospital->id, 'status', 'active'));
        $this->assertSame(1, $this->tenantTableCount('admissions', $hospital->id, 'status', 'discharged'));
        $this->assertSame(8, $this->tenantTableCount('admission_notes', $hospital->id));
        $this->assertSame(53, $this->tenantTableCount('invoices', $hospital->id));
        $this->assertGreaterThanOrEqual(5, $this->tenantTableCount('medication_administrations', $hospital->id));
        $this->assertSame(2, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('type', 'lab_only')->whereNotNull('referred_from')->count());
        $this->assertGreaterThan(0, $this->tenantTableCount('lab_requests', $hospital->id, 'status', 'completed'));
        $this->assertGreaterThan(0, $this->tenantTableCount('lab_requests', $hospital->id, 'status', 'pending'));
        $this->assertGreaterThan(0, $this->tenantTableCount('prescriptions', $hospital->id, 'status', 'completed'));
        $this->assertGreaterThan(0, $this->tenantTableCount('prescriptions', $hospital->id, 'status', 'pending'));
        $this->assertGreaterThan(0, Invoice::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('status', 'paid')->count());
        $this->assertGreaterThan(0, Invoice::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('status', 'partial')->count());
        $this->assertGreaterThan(0, Invoice::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('status', 'unpaid')->count());
        $this->assertSame(0, Patient::withoutGlobalScopes()->where('hospital_id', $untouchedHospital->id)->count());
        $this->assertSame(1, DB::table('departments')->where('hospital_id', $hospital->id)->where('name', 'Urology Ward')->where('has_ward', true)->count());
        $this->assertSame(1, DB::table('departments')->where('hospital_id', $hospital->id)->where('name', 'Theatre & Anaesthesia')->count());
        $this->assertSame(1, DB::table('departments')->where('hospital_id', $hospital->id)->where('name', 'Diagnostic Imaging')->count());

        $patients = Patient::withoutGlobalScopes()->where('hospital_id', $hospital->id)->oldest('id')->get();
        $this->assertSame(2, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('patient_id', $patients[0]->id)->count());
        $this->assertSame(0, Visit::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('patient_id', $patients[29]->id)->count());

        $visitCount = $this->tenantTableCount('visits', $hospital->id);
        $paymentCount = $this->tenantTableCount('payments', $hospital->id);
        $stockQuantity = Drug::withoutGlobalScopes()->where('hospital_id', $hospital->id)->sum('stock_quantity');

        $this->artisan('hospital:seed-urology-demo', ['--hospital' => $hospital->id])->assertSuccessful();

        $this->assertSame($visitCount, $this->tenantTableCount('visits', $hospital->id));
        $this->assertSame($paymentCount, $this->tenantTableCount('payments', $hospital->id));
        $this->assertSame($stockQuantity, Drug::withoutGlobalScopes()->where('hospital_id', $hospital->id)->sum('stock_quantity'));
    }

    public function test_command_requires_a_positive_hospital_id(): void
    {
        $this->artisan('hospital:seed-urology-demo')
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
