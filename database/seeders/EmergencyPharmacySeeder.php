<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\EmergencyItemUse;
use App\Models\EmergencyPharmacyItem;
use App\Models\EmergencyPharmacyItemMovement;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmergencyPharmacySeeder extends Seeder
{
    /**
     * Seed an A&E-owned starter catalogue. Existing stock balances are preserved
     * when this seeder is run again so it cannot silently restock used items.
     */
    public function run(): void
    {
        $department = Department::firstOrCreate(
            ['name' => 'Accident & Emergency'],
            ['description' => 'Emergency assessment and urgent care.'],
        );

        $manager = User::firstOrCreate(
            ['email' => 'er.pharmacist@hms.test'],
            [
                'name' => 'A&E Pharmacist Demo',
                'role' => Role::Pharmacist,
                'department_id' => $department->id,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // Correct the department if an earlier demo run created this account first.
        if ($manager->department_id !== $department->id || ! $manager->hasRole(Role::Pharmacist)) {
            $manager->update(['department_id' => $department->id, 'role' => Role::Pharmacist]);
        }

        $catalogue = [
            ['name' => 'Paracetamol 500 mg tablet', 'category' => 'medicine', 'unit' => 'tablet', 'unit_price' => 50, 'quantity' => 100, 'reorder_level' => 20],
            ['name' => 'Artemether 80 mg/ml injection', 'category' => 'injection', 'unit' => 'ampoule', 'unit_price' => 2200, 'quantity' => 12, 'reorder_level' => 3],
            ['name' => 'Normal saline 0.9% 500 ml', 'category' => 'medicine', 'unit' => 'bag', 'unit_price' => 1800, 'quantity' => 20, 'reorder_level' => 5],
            ['name' => 'Sterile gauze', 'category' => 'consumable', 'unit' => 'pack', 'unit_price' => 700, 'quantity' => 40, 'reorder_level' => 10],
            ['name' => 'Cotton wool', 'category' => 'consumable', 'unit' => 'roll', 'unit_price' => 900, 'quantity' => 25, 'reorder_level' => 5],
            ['name' => 'Examination gloves', 'category' => 'consumable', 'unit' => 'pair', 'unit_price' => 250, 'quantity' => 100, 'reorder_level' => 20],
            ['name' => 'Disposable syringe 5 ml', 'category' => 'consumable', 'unit' => 'piece', 'unit_price' => 180, 'quantity' => 80, 'reorder_level' => 20],
            ['name' => 'IV giving set', 'category' => 'consumable', 'unit' => 'piece', 'unit_price' => 1200, 'quantity' => 15, 'reorder_level' => 4],
            ['name' => 'IV cannula 18G', 'category' => 'consumable', 'unit' => 'piece', 'unit_price' => 650, 'quantity' => 30, 'reorder_level' => 8],
            ['name' => 'Adhesive dressing tape', 'category' => 'consumable', 'unit' => 'roll', 'unit_price' => 600, 'quantity' => 20, 'reorder_level' => 5],
            ['name' => 'Oxygen face mask', 'category' => 'equipment', 'unit' => 'piece', 'unit_price' => 1500, 'quantity' => 10, 'reorder_level' => 3],
            ['name' => 'Surgical face mask', 'category' => 'consumable', 'unit' => 'piece', 'unit_price' => 100, 'quantity' => 100, 'reorder_level' => 25],
        ];

        foreach ($catalogue as $details) {
            $item = EmergencyPharmacyItem::query()->firstOrCreate(
                ['name' => $details['name']],
                [...$details, 'is_active' => true, 'created_by' => $manager->id, 'updated_by' => $manager->id],
            );

            if ($item->wasRecentlyCreated) {
                EmergencyPharmacyItemMovement::create([
                    'emergency_pharmacy_item_id' => $item->id,
                    'user_id' => $manager->id,
                    'type' => 'stock_added',
                    'quantity_change' => $item->quantity,
                    'stock_before' => 0,
                    'stock_after' => $item->quantity,
                    'notes' => 'Demo A&E opening stock. Adjust or replace with actual counts and prices.',
                ]);
            }
        }

        $demoPatient = Patient::query()->where('email', 'demo.active-care@hms.test')->first();
        $demoVisit = $demoPatient?->visits()
            ->where('is_emergency', true)
            ->where('status', VisitStatus::InConsultation->value)
            ->first();
        $gauze = EmergencyPharmacyItem::query()->where('name', 'Sterile gauze')->first();

        if ($demoVisit && $gauze && ! EmergencyItemUse::query()
            ->where('visit_id', $demoVisit->id)
            ->where('emergency_pharmacy_item_id', $gauze->id)
            ->exists() && $gauze->quantity >= 2) {
            DB::transaction(function () use ($demoVisit, $gauze, $manager) {
                $gauze = EmergencyPharmacyItem::query()->lockForUpdate()->findOrFail($gauze->id);
                if ($gauze->quantity < 2) {
                    return;
                }

                $before = (int) $gauze->quantity;
                $gauze->decrement('quantity', 2, ['updated_by' => $manager->id]);
                $use = EmergencyItemUse::create([
                    'visit_id' => $demoVisit->id,
                    'emergency_pharmacy_item_id' => $gauze->id,
                    'quantity' => 2,
                    'unit_price' => $gauze->unit_price,
                    'notes' => 'DEMO: Sterile gauze used during emergency treatment.',
                    'recorded_by' => $manager->id,
                ]);
                EmergencyPharmacyItemMovement::create([
                    'emergency_pharmacy_item_id' => $gauze->id,
                    'visit_id' => $demoVisit->id,
                    'user_id' => $manager->id,
                    'type' => 'patient_use',
                    'quantity_change' => -2,
                    'stock_before' => $before,
                    'stock_after' => $before - 2,
                    'notes' => 'DEMO: Two packs used for A&E active-care visit.',
                ]);

                $invoice = app(BillingService::class)->invoiceFor($demoVisit);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'category' => 'emergency_item',
                    'description' => $gauze->name,
                    'quantity' => 2,
                    'unit_price' => $use->unit_price,
                    'billable_type' => $use->getMorphClass(),
                    'billable_id' => $use->id,
                ]);
            });
        }
    }
}
