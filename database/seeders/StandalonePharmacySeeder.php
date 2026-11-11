<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Drug;
use App\Models\DrugStockBatch;
use App\Models\DrugStockMovement;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\PharmacySale;
use App\Models\User;
use App\Services\StandalonePharmacySaleService;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StandalonePharmacySeeder extends Seeder
{
    /** Change this value to seed a different standalone pharmacy. */
    public int $hospitalId = 13;

    private const DEMO_SALE_PREFIX = 'SP-DEMO-';

    private const PASSWORD = 'password';

    /**
     * @var array<string, array{name: string, generic_name: string, form: string, strength: string, price: int, reorder: int, batches: array<int, array{number: string, quantity: int, expires_in_days: int}>}>
     */
    private const PRODUCTS = [
        'paracetamol' => ['name' => 'Emzor Paracetamol', 'generic_name' => 'Paracetamol', 'form' => 'Tablet', 'strength' => '500mg', 'price' => 100, 'reorder' => 30, 'batches' => [['number' => 'PCM-A01', 'quantity' => 80, 'expires_in_days' => 150], ['number' => 'PCM-B02', 'quantity' => 70, 'expires_in_days' => 420]]],
        'amoxicillin' => ['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'form' => 'Capsule', 'strength' => '500mg', 'price' => 350, 'reorder' => 15, 'batches' => [['number' => 'AMX-A11', 'quantity' => 55, 'expires_in_days' => 18]]],
        'antimalarial' => ['name' => 'Lonart DS', 'generic_name' => 'Artemether/Lumefantrine', 'form' => 'Tablet', 'strength' => '80mg/480mg', 'price' => 3200, 'reorder' => 10, 'batches' => [['number' => 'LNT-C12', 'quantity' => 35, 'expires_in_days' => 300]]],
        'ciprofloxacin' => ['name' => 'Ciprofloxacin', 'generic_name' => 'Ciprofloxacin', 'form' => 'Tablet', 'strength' => '500mg', 'price' => 500, 'reorder' => 10, 'batches' => [['number' => 'CIP-D08', 'quantity' => 48, 'expires_in_days' => 240]]],
        'metronidazole' => ['name' => 'Flagyl', 'generic_name' => 'Metronidazole', 'form' => 'Tablet', 'strength' => '400mg', 'price' => 180, 'reorder' => 20, 'batches' => [['number' => 'MTZ-E05', 'quantity' => 85, 'expires_in_days' => 365]]],
        'amlodipine' => ['name' => 'Amlodipine', 'generic_name' => 'Amlodipine', 'form' => 'Tablet', 'strength' => '10mg', 'price' => 250, 'reorder' => 15, 'batches' => [['number' => 'AML-F04', 'quantity' => 42, 'expires_in_days' => 85]]],
        'losartan' => ['name' => 'Losartan', 'generic_name' => 'Losartan Potassium', 'form' => 'Tablet', 'strength' => '50mg', 'price' => 300, 'reorder' => 15, 'batches' => [['number' => 'LOS-G14', 'quantity' => 45, 'expires_in_days' => 450]]],
        'omeprazole' => ['name' => 'Omeprazole', 'generic_name' => 'Omeprazole', 'form' => 'Capsule', 'strength' => '20mg', 'price' => 200, 'reorder' => 20, 'batches' => [['number' => 'OMP-H22', 'quantity' => 70, 'expires_in_days' => 275]]],
        'vitamin_c' => ['name' => 'Emvit C', 'generic_name' => 'Ascorbic Acid', 'form' => 'Tablet', 'strength' => '100mg', 'price' => 80, 'reorder' => 25, 'batches' => [['number' => 'VTC-J17', 'quantity' => 95, 'expires_in_days' => 500]]],
        'ors' => ['name' => 'Oral Rehydration Salts', 'generic_name' => 'Oral Rehydration Salts', 'form' => 'Sachet', 'strength' => '20.5g', 'price' => 400, 'reorder' => 12, 'batches' => [['number' => 'ORS-EX1', 'quantity' => 6, 'expires_in_days' => -14], ['number' => 'ORS-K03', 'quantity' => 30, 'expires_in_days' => 190]]],
        'cetirizine' => ['name' => 'Cetirizine', 'generic_name' => 'Cetirizine Hydrochloride', 'form' => 'Tablet', 'strength' => '10mg', 'price' => 150, 'reorder' => 15, 'batches' => [['number' => 'CTZ-L09', 'quantity' => 44, 'expires_in_days' => 330]]],
        'ibuprofen' => ['name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'form' => 'Tablet', 'strength' => '400mg', 'price' => 200, 'reorder' => 20, 'batches' => [['number' => 'IBU-M06', 'quantity' => 52, 'expires_in_days' => 210]]],
        'azithromycin' => ['name' => 'Azithromycin', 'generic_name' => 'Azithromycin', 'form' => 'Tablet', 'strength' => '500mg', 'price' => 1000, 'reorder' => 10, 'batches' => [['number' => 'AZM-N10', 'quantity' => 28, 'expires_in_days' => 310]]],
        'cough_syrup' => ['name' => 'Benylin Cough Syrup', 'generic_name' => 'Diphenhydramine', 'form' => 'Syrup', 'strength' => '100ml', 'price' => 2800, 'reorder' => 8, 'batches' => [['number' => 'BCS-P02', 'quantity' => 18, 'expires_in_days' => 55]]],
        'diclofenac_gel' => ['name' => 'Diclofenac Gel', 'generic_name' => 'Diclofenac Sodium', 'form' => 'Gel', 'strength' => '1%', 'price' => 1800, 'reorder' => 8, 'batches' => [['number' => 'DCG-Q07', 'quantity' => 20, 'expires_in_days' => 390]]],
    ];

    /** @var array<int, array{name: string, role: Role, email: string}> */
    private const STAFF = [
        ['name' => 'Chukwudi Okafor', 'role' => Role::Admin, 'email' => 'chukwudi.okafor'],
        ['name' => 'Chiamaka Nwosu', 'role' => Role::ChiefPharmacist, 'email' => 'chiamaka.nwosu'],
        ['name' => 'Ifeoma Eze', 'role' => Role::Pharmacist, 'email' => 'ifeoma.eze'],
        ['name' => 'Obinna Nwankwo', 'role' => Role::Pharmacist, 'email' => 'obinna.nwankwo'],
        ['name' => 'Adaeze Onuoha', 'role' => Role::Cashier, 'email' => 'adaeze.onuoha'],
    ];

    public function run(StandalonePharmacySaleService $sales): void
    {
        $hospital = Hospital::query()->find($this->hospitalId);

        if (! $hospital) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} does not exist.");
        }

        if (! $hospital->isStandalonePharmacy()) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} is not a standalone pharmacy.");
        }

        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set($this->hospitalId);

        try {
            if (PharmacySale::query()->whereLike('sale_number', self::DEMO_SALE_PREFIX.$this->hospitalId.'-%')->exists()) {
                $this->command?->info("Standalone pharmacy demo data already exists for hospital ID {$this->hospitalId}; no changes made.");

                return;
            }

            DB::transaction(function () use ($sales): void {
                $department = $this->seedConfiguration();
                $staff = $this->seedStaff($department);
                $products = $this->seedProductsAndBatches($staff['chief_pharmacist']);
                $this->seedSales($sales, $products, $staff['cashier']);
            }, 3);
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info("Seeded standalone pharmacy staff, inventory, expiry alerts, and seven-day sales for hospital ID {$this->hospitalId}.");
        $this->command?->warn('Demo login password: '.self::PASSWORD);
    }

    private function seedConfiguration(): Department
    {
        foreach (['pharmacy', 'finance_reports'] as $module) {
            HospitalModule::updateOrCreate(['module' => $module], ['enabled' => true]);
        }

        return Department::updateOrCreate(
            ['name' => Department::PHARMACY_NAME],
            ['description' => 'Retail pharmacy operations, stock control, dispensing, and sales.', 'has_ward' => false],
        );
    }

    /** @return array{chief_pharmacist: User, cashier: User} */
    private function seedStaff(Department $department): array
    {
        $staff = [];

        foreach (self::STAFF as $details) {
            $user = User::updateOrCreate(
                ['email' => $details['email'].'.sp'.$this->hospitalId.'@demo.test'],
                [
                    'name' => $details['name'],
                    'role' => $details['role'],
                    'department_id' => $department->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::PASSWORD),
                ],
            );

            if ($details['role'] === Role::ChiefPharmacist) {
                $staff['chief_pharmacist'] = $user;
                $department->update(['hod_id' => $user->id]);
            }

            if ($details['role'] === Role::Cashier) {
                $staff['cashier'] = $user;
            }
        }

        return $staff;
    }

    /** @return array<string, Drug> */
    private function seedProductsAndBatches(User $receiver): array
    {
        $products = [];

        foreach (self::PRODUCTS as $key => $details) {
            $drug = Drug::firstOrCreate(
                ['name' => $details['name'], 'strength' => $details['strength'], 'form' => $details['form']],
                [
                    'generic_name' => $details['generic_name'],
                    'unit_price' => $details['price'],
                    'stock_quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_level' => $details['reorder'],
                    'is_fluid' => false,
                    'is_active' => true,
                ],
            );

            foreach ($details['batches'] as $batchDetails) {
                $batch = DrugStockBatch::firstOrCreate(
                    ['drug_id' => $drug->id, 'batch_number' => 'SP'.$this->hospitalId.'-'.$batchDetails['number']],
                    [
                        'quantity_received' => $batchDetails['quantity'],
                        'quantity_remaining' => $batchDetails['quantity'],
                        'expiry_date' => today()->addDays($batchDetails['expires_in_days']),
                        'received_by' => $receiver->id,
                        'received_at' => now()->subDays(45),
                    ],
                );

                if ($batch->wasRecentlyCreated) {
                    $stockBefore = (int) $drug->stock_quantity;
                    $drug->increment('stock_quantity', $batchDetails['quantity']);
                    $drug->refresh();

                    DrugStockMovement::create([
                        'drug_id' => $drug->id,
                        'user_id' => $receiver->id,
                        'type' => 'opening_balance',
                        'quantity_change' => $batchDetails['quantity'],
                        'stock_before' => $stockBefore,
                        'stock_after' => (int) $drug->stock_quantity,
                        'notes' => 'Standalone pharmacy demo opening batch '.$batch->batch_number.'.',
                    ]);
                }
            }

            $products[$key] = $drug->fresh();
        }

        return $products;
    }

    /** @param array<string, Drug> $products */
    private function seedSales(StandalonePharmacySaleService $sales, array $products, User $cashier): void
    {
        $demoSales = [
            [6, 'cash', 'Ngozi Okeke', [['paracetamol', 4], ['vitamin_c', 5]], 0],
            [5, 'pos', 'Chinedu Nwosu', [['antimalarial', 1], ['paracetamol', 6]], 200],
            [4, 'transfer', 'Adaobi Eze', [['amlodipine', 5], ['losartan', 5]], 0],
            [4, 'cash', 'Ifeanyi Obi', [['metronidazole', 7], ['ors', 2]], 0],
            [3, 'pos', 'Nneka Umeh', [['cough_syrup', 1], ['cetirizine', 3]], 0],
            [3, 'cash', 'Obinna Nwankwo', [['ciprofloxacin', 4], ['omeprazole', 4]], 0],
            [2, 'transfer', 'Chiamaka Madu', [['azithromycin', 3], ['paracetamol', 5]], 250],
            [2, 'pos', 'Kelechi Onoh', [['ibuprofen', 5], ['diclofenac_gel', 1]], 0],
            [1, 'cash', 'Ugochi Anyanwu', [['amoxicillin', 6], ['vitamin_c', 4]], 0],
            [1, 'transfer', 'Emeka Onuoha', [['antimalarial', 1], ['ors', 3]], 0],
            [0, 'pos', 'Ijeoma Nnamani', [['paracetamol', 8], ['cetirizine', 4]], 100],
            [0, 'cash', 'Somtochukwu Ezeani', [['omeprazole', 5], ['metronidazole', 5]], 0],
        ];

        foreach ($demoSales as $index => [$daysAgo, $paymentMethod, $customerName, $items, $discount]) {
            $sale = $sales->createSale([
                'customer_name' => $customerName,
                'customer_phone' => '0803'.str_pad((string) ($this->hospitalId * 100000 + $index), 7, '0', STR_PAD_LEFT),
                'payment_method' => $paymentMethod,
                'discount' => $discount,
                'items' => collect($items)->map(fn (array $item): array => [
                    'drug_id' => $products[$item[0]]->id,
                    'quantity' => $item[1],
                ])->all(),
            ], $cashier->id);

            $saleNumber = self::DEMO_SALE_PREFIX.$this->hospitalId.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $sale->update([
                'sale_number' => $saleNumber,
                'sold_at' => now()->subDays($daysAgo)->setTime(9 + ($index % 8), ($index * 7) % 60),
            ]);

            DrugStockMovement::query()
                ->whereIn('pharmacy_sale_item_id', $sale->items()->pluck('id'))
                ->update(['notes' => 'Standalone pharmacy sale '.$saleNumber.'.']);
        }
    }
}
