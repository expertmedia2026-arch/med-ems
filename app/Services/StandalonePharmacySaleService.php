<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugStockBatch;
use App\Models\DrugStockMovement;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Support\HospitalContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StandalonePharmacySaleService
{
    public function __construct(private readonly HospitalContext $context) {}

    /** @return array<string, mixed> */
    public function dashboardData(array $filters = []): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? now()->toDateString())->endOfDay();
        $periodSales = PharmacySale::query()->where('status', 'completed')->whereBetween('sold_at', [$start, $end]);
        $expiringBatches = DrugStockBatch::query()
            ->with('drug:id,name,strength,form')
            ->where('quantity_remaining', '>', 0)
            ->whereDate('expiry_date', '<=', today()->addDays(90))
            ->orderBy('expiry_date')
            ->get();

        return [
            'filters' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            'rangeLabel' => $start->format('d M Y').' – '.$end->format('d M Y'),
            'sales' => PharmacySale::query()->with('seller:id,name')->withCount('items')->whereBetween('sold_at', [$start, $end])->latest('sold_at')->paginate(20)->withQueryString(),
            'todaySummary' => [
                'sales' => (clone $periodSales)->count(),
                'revenue' => (float) (clone $periodSales)->sum('total'),
                'items' => (int) PharmacySaleItem::query()->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('sold_at', [$start, $end]))->sum('quantity'),
            ],
            'expiredBatches' => $expiringBatches->filter(fn (DrugStockBatch $batch) => $batch->expiry_date->isPast())->values(),
            'expiringSoonBatches' => $expiringBatches->reject(fn (DrugStockBatch $batch) => $batch->expiry_date->isPast())->values(),
        ];
    }

    /** @return Collection<int, Drug> */
    public function productsForSale()
    {
        return Drug::query()
            ->active()
            ->with(['stockBatches' => fn ($query) => $query->where('quantity_remaining', '>', 0)->orderBy('expiry_date')])
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createSale(array $data, int $userId): PharmacySale
    {
        return DB::transaction(function () use ($data, $userId): PharmacySale {
            $preparedItems = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $index => $input) {
                $drug = Drug::query()->active()->lockForUpdate()->findOrFail($input['drug_id']);
                $quantity = (int) $input['quantity'];
                $batches = DrugStockBatch::query()
                    ->where('drug_id', $drug->id)
                    ->where('quantity_remaining', '>', 0)
                    ->whereDate('expiry_date', '>=', today())
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ((int) $batches->sum('quantity_remaining') < $quantity || $drug->available_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "{$drug->display_name} does not have enough unexpired stock.",
                    ]);
                }

                $lineTotal = round((float) $drug->unit_price * $quantity, 2);
                $subtotal += $lineTotal;
                $preparedItems[] = compact('drug', 'quantity', 'batches', 'lineTotal');
            }

            $discount = round((float) ($data['discount'] ?? 0), 2);
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'The discount cannot exceed the sale subtotal.']);
            }

            $sale = PharmacySale::create([
                'sale_number' => 'SP-'.$this->context->id().'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'status' => 'completed',
                'sold_by' => $userId,
                'sold_at' => now(),
            ]);

            foreach ($preparedItems as $prepared) {
                /** @var Drug $drug */
                $drug = $prepared['drug'];
                $remaining = $prepared['quantity'];
                $allocations = [];

                foreach ($prepared['batches'] as $batch) {
                    if ($remaining === 0) {
                        break;
                    }

                    $used = min($remaining, (int) $batch->quantity_remaining);
                    $batch->decrement('quantity_remaining', $used);
                    $allocations[] = [
                        'batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'expiry_date' => $batch->expiry_date->toDateString(),
                        'quantity' => $used,
                    ];
                    $remaining -= $used;
                }

                $item = PharmacySaleItem::create([
                    'pharmacy_sale_id' => $sale->id,
                    'drug_id' => $drug->id,
                    'drug_name' => $drug->display_name,
                    'quantity' => $prepared['quantity'],
                    'unit_price' => $drug->unit_price,
                    'total' => $prepared['lineTotal'],
                    'batch_allocations' => $allocations,
                ]);
                $stockBefore = (int) $drug->stock_quantity;
                $drug->decrement('stock_quantity', $prepared['quantity']);
                DrugStockMovement::create([
                    'drug_id' => $drug->id,
                    'pharmacy_sale_item_id' => $item->id,
                    'user_id' => $userId,
                    'type' => 'retail_sale',
                    'quantity_change' => -$prepared['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore - $prepared['quantity'],
                    'notes' => 'Standalone pharmacy sale '.$sale->sale_number.'.',
                ]);
            }

            return $sale->load(['items', 'seller']);
        });
    }
}
