<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Drug;
use App\Models\DrugStockBatch;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\PharmacySale;
use App\Models\User;
use App\Services\GlobalAdminHospitalService;
use Database\Seeders\StandalonePharmacySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandalonePharmacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_pharmacy_seeder_populates_a_complete_reusable_demo_dataset(): void
    {
        [$pharmacy] = $this->standalonePharmacy();
        $seeder = app(StandalonePharmacySeeder::class);
        $seeder->hospitalId = $pharmacy->id;

        app()->call([$seeder, 'run']);

        $this->assertDatabaseHas('users', [
            'hospital_id' => $pharmacy->id,
            'name' => 'Chiamaka Nwosu',
            'role' => Role::ChiefPharmacist->value,
        ]);
        $this->assertSame(15, Drug::query()->where('hospital_id', $pharmacy->id)->count());
        $this->assertSame(17, DrugStockBatch::query()->where('hospital_id', $pharmacy->id)->count());
        $this->assertSame(12, PharmacySale::query()->where('hospital_id', $pharmacy->id)->count());
        $this->assertDatabaseHas('drug_stock_batches', [
            'hospital_id' => $pharmacy->id,
            'batch_number' => 'SP'.$pharmacy->id.'-ORS-EX1',
            'quantity_remaining' => 6,
        ]);

        app()->call([$seeder, 'run']);

        $this->assertSame(15, Drug::query()->where('hospital_id', $pharmacy->id)->count());
        $this->assertSame(12, PharmacySale::query()->where('hospital_id', $pharmacy->id)->count());
    }

    public function test_platform_can_onboard_a_standalone_pharmacy_with_required_defaults(): void
    {
        $platformAdmin = User::factory()->create([
            'hospital_id' => null,
            'role' => Role::Admin,
            'is_global_admin' => true,
            'is_platform_super_admin' => true,
        ]);

        $result = app(GlobalAdminHospitalService::class)->createHospital([
            'name' => 'Chinonso Community Pharmacy',
            'facility_type' => 'standalone_pharmacy',
            'admin_name' => 'Chinonso Okeke',
            'admin_email' => 'chinonso@example.test',
            'admission_template' => 'basic',
        ], $platformAdmin);

        $pharmacy = $result['hospital'];
        $this->assertTrue($pharmacy->isStandalonePharmacy());
        $this->assertDatabaseHas('departments', ['hospital_id' => $pharmacy->id, 'name' => Department::PHARMACY_NAME]);
        $this->assertDatabaseHas('hospital_modules', ['hospital_id' => $pharmacy->id, 'module' => 'pharmacy', 'enabled' => true]);
        $this->assertDatabaseHas('hospital_modules', ['hospital_id' => $pharmacy->id, 'module' => 'finance_reports', 'enabled' => true]);
        $this->assertDatabaseMissing('hospital_workflows', ['hospital_id' => $pharmacy->id, 'workflow' => 'admission']);
    }

    public function test_completed_sale_uses_earliest_expiring_stock_and_creates_a_receipt(): void
    {
        [$pharmacy, $admin] = $this->standalonePharmacy();
        $drug = Drug::create([
            'hospital_id' => $pharmacy->id,
            'name' => 'Ciprofloxacin',
            'form' => 'Tablet',
            'strength' => '500mg',
            'unit_price' => 500,
            'stock_quantity' => 15,
            'reserved_quantity' => 0,
            'reorder_level' => 3,
            'is_active' => true,
        ]);
        $earlyBatch = DrugStockBatch::create([
            'hospital_id' => $pharmacy->id,
            'drug_id' => $drug->id,
            'batch_number' => 'EARLY-01',
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'expiry_date' => today()->addMonths(2),
            'received_by' => $admin->id,
            'received_at' => now(),
        ]);
        $laterBatch = DrugStockBatch::create([
            'hospital_id' => $pharmacy->id,
            'drug_id' => $drug->id,
            'batch_number' => 'LATER-02',
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiry_date' => today()->addYear(),
            'received_by' => $admin->id,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('standalone-pharmacy.sales.store'), [
            'customer_name' => 'Adaobi Nwankwo',
            'customer_phone' => '08030000000',
            'payment_method' => 'pos',
            'discount' => 500,
            'items' => [['drug_id' => $drug->id, 'quantity' => 7]],
        ]);

        $sale = PharmacySale::query()->firstOrFail();
        $response->assertRedirect(route('standalone-pharmacy.sales.show', $sale));
        $this->assertSame('3000.00', $sale->total);
        $this->assertSame(0, $earlyBatch->refresh()->quantity_remaining);
        $this->assertSame(8, $laterBatch->refresh()->quantity_remaining);
        $this->assertSame(8, $drug->refresh()->stock_quantity);
        $this->assertDatabaseHas('drug_stock_movements', [
            'drug_id' => $drug->id,
            'type' => 'retail_sale',
            'quantity_change' => -7,
            'stock_after' => 8,
        ]);

        $this->actingAs($admin)->get(route('standalone-pharmacy.sales.show', $sale))
            ->assertOk()
            ->assertSee('Adaobi Nwankwo')
            ->assertSee('Ciprofloxacin')
            ->assertSee('3,000.00');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Revenue over the last 7 days')
            ->assertSee('Payment methods')
            ->assertSee('Expiry risk')
            ->assertViewHas('standaloneCharts', function (array $charts): bool {
                $today = collect($charts['revenueTrend'])->last();
                $pos = collect($charts['paymentMethods'])->firstWhere('method', 'Pos');

                return $today['amount'] === 3000.0
                    && $today['sales'] === 1
                    && $pos['amount'] === 3000.0;
            });
    }

    public function test_expired_stock_is_alerted_and_cannot_be_sold(): void
    {
        [$pharmacy, $admin] = $this->standalonePharmacy();
        $drug = Drug::create([
            'hospital_id' => $pharmacy->id,
            'name' => 'Expired Product',
            'unit_price' => 100,
            'stock_quantity' => 4,
            'reserved_quantity' => 0,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
        DrugStockBatch::create([
            'hospital_id' => $pharmacy->id,
            'drug_id' => $drug->id,
            'quantity_received' => 4,
            'quantity_remaining' => 4,
            'expiry_date' => today()->subDay(),
            'received_by' => $admin->id,
            'received_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('standalone-pharmacy.sales.index'))
            ->assertOk()
            ->assertSee('Expired Product')
            ->assertSee('Expired stock');

        $this->actingAs($admin)->post(route('standalone-pharmacy.sales.store'), [
            'payment_method' => 'cash',
            'items' => [['drug_id' => $drug->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(4, $drug->refresh()->stock_quantity);
        $this->assertDatabaseCount('pharmacy_sales', 0);
    }

    public function test_standalone_product_requires_expiry_and_creates_opening_batch(): void
    {
        [$pharmacy, $admin] = $this->standalonePharmacy();

        $this->actingAs($admin)->get(route('pharmacy.drugs.create'))
            ->assertOk()
            ->assertDontSee('This is a prescribed fluid');

        $payload = [
            'name' => 'Amoxicillin',
            'generic_name' => 'Amoxicillin',
            'form' => 'Capsule',
            'strength' => '500mg',
            'unit_price' => 250,
            'reorder_level' => 10,
            'stock_quantity' => 50,
        ];

        $this->actingAs($admin)->post(route('pharmacy.drugs.store'), $payload)
            ->assertSessionHasErrors('expiry_date');

        $this->actingAs($admin)->post(route('pharmacy.drugs.store'), $payload + [
            'expiry_date' => today()->addYear()->toDateString(),
            'batch_number' => 'AMX-100',
        ])->assertRedirect(route('pharmacy.inventory'));

        $drug = Drug::query()->where('name', 'Amoxicillin')->firstOrFail();
        $this->assertDatabaseHas('drug_stock_batches', [
            'hospital_id' => $pharmacy->id,
            'drug_id' => $drug->id,
            'batch_number' => 'AMX-100',
            'quantity_remaining' => 50,
        ]);
    }

    public function test_hospital_workspace_cannot_access_standalone_sales(): void
    {
        $hospital = Hospital::create(['name' => 'Hospital', 'slug' => 'hospital', 'facility_type' => 'hospital', 'is_active' => true]);
        HospitalSetting::create(['hospital_id' => $hospital->id, 'name' => $hospital->name]);
        HospitalModule::create(['hospital_id' => $hospital->id, 'module' => 'pharmacy', 'enabled' => true]);
        $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => Role::Admin, 'is_active' => true]);

        $this->actingAs($admin)->get(route('pharmacy.drugs.create'))
            ->assertOk()
            ->assertSee('This is a prescribed fluid');

        $this->actingAs($admin)->get(route('standalone-pharmacy.sales.index'))->assertForbidden();
    }

    public function test_pharmacy_cannot_open_another_pharmacys_receipt(): void
    {
        [, $firstAdmin] = $this->standalonePharmacy();
        [, $secondAdmin] = $this->standalonePharmacy();
        $sale = PharmacySale::create([
            'hospital_id' => $firstAdmin->hospital_id,
            'sale_number' => 'SP-CROSS-TENANT',
            'payment_method' => 'cash',
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'status' => 'completed',
            'sold_by' => $firstAdmin->id,
            'sold_at' => now(),
        ]);

        $this->actingAs($secondAdmin)
            ->get(route('standalone-pharmacy.sales.show', $sale->id))
            ->assertNotFound();
    }

    public function test_standalone_pharmacy_settings_hide_and_block_hospital_workflows_and_payments(): void
    {
        [, $admin] = $this->standalonePharmacy();

        $this->actingAs($admin)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Workspace Settings')
            ->assertDontSee('Admission workflow')
            ->assertDontSee('Payments');

        $this->actingAs($admin)->get(route('settings.section', ['section' => 'payments']))->assertNotFound();
        $this->actingAs($admin)->put(route('settings.update.section', ['section' => 'payments']))->assertNotFound();
        $this->actingAs($admin)->get(route('settings.admission-workflow'))->assertNotFound();
        $this->actingAs($admin)->put(route('settings.admission-workflow.update'))->assertNotFound();
    }

    public function test_platform_configuration_omits_admissions_and_rejects_payment_modules_for_standalone_pharmacy(): void
    {
        [$pharmacy] = $this->standalonePharmacy();
        $platformAdmin = User::factory()->create([
            'hospital_id' => null,
            'role' => Role::Admin,
            'is_global_admin' => true,
            'is_platform_super_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin)->get(route('global-admin.hospitals.edit', $pharmacy))
            ->assertOk()
            ->assertSee('standalone pharmacy')
            ->assertDontSee('Admission workflow starting template')
            ->assertDontSee('Online Payments');

        $this->actingAs($platformAdmin)->put(route('global-admin.hospitals.update', $pharmacy), [
            'modules' => ['pharmacy', 'online_payments'],
        ])->assertRedirect(route('global-admin.hospitals.index'));

        $this->assertDatabaseHas('hospital_modules', [
            'hospital_id' => $pharmacy->id,
            'module' => 'pharmacy',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('hospital_modules', [
            'hospital_id' => $pharmacy->id,
            'module' => 'online_payments',
            'enabled' => false,
        ]);
        $this->assertDatabaseMissing('hospital_workflows', ['hospital_id' => $pharmacy->id]);
    }

    /** @return array{Hospital, User} */
    private function standalonePharmacy(): array
    {
        $hospital = Hospital::create([
            'name' => 'Ogechi Pharmacy',
            'slug' => 'ogechi-pharmacy-'.fake()->unique()->numberBetween(100, 999),
            'facility_type' => 'standalone_pharmacy',
            'is_active' => true,
        ]);
        HospitalSetting::create(['hospital_id' => $hospital->id, 'name' => $hospital->name]);
        HospitalModule::create(['hospital_id' => $hospital->id, 'module' => 'pharmacy', 'enabled' => true]);
        $department = Department::create(['hospital_id' => $hospital->id, 'name' => Department::PHARMACY_NAME]);
        $admin = User::factory()->create([
            'hospital_id' => $hospital->id,
            'department_id' => $department->id,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        return [$hospital, $admin];
    }
}
