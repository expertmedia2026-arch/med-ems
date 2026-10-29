<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\EmergencyItemUse;
use App\Models\EmergencyPharmacyItem;
use App\Models\EmergencyPharmacyItemMovement;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Manages the separate A&E stock catalogue, use ledger, and related invoice charges.
 */
class EmergencyPharmacyService
{
    /** Inject billing support for charging recorded A&E item use to the visit invoice. */
    public function __construct(private readonly BillingService $billing) {}

    /** Return the emergency stock screen data after checking the viewer's A&E pharmacy access. */
    public function indexData(User $user): array
    {
        abort_unless($user->canAccessEmergencyPharmacy(), 403);

        return [
            'items' => EmergencyPharmacyItem::query()->orderBy('name')->paginate(25),
            'movements' => EmergencyPharmacyItemMovement::with(['item', 'user', 'visit.patient'])->latest()->limit(50)->get(),
            'canManage' => $user->canManageEmergencyPharmacy(),
        ];
    }

    /** List active A&E catalogue items that currently have stock for use during a visit. */
    public function useOptions(): Collection
    {
        return EmergencyPharmacyItem::query()->where('is_active', true)->where('quantity', '>', 0)->orderBy('name')->get();
    }

    /**
     * Create an A&E stock item and its opening movement in one transaction.
     *
     * Only users permitted to manage emergency pharmacy stock may add catalogue items.
     *
     * @param array<string, mixed> $data Validated item description, unit, quantity, and price fields.
     */
    public function addItem(array $data, User $actor): EmergencyPharmacyItem
    {
        abort_unless($actor->canManageEmergencyPharmacy(), 403);

        return DB::transaction(function () use ($data, $actor) {
            $item = EmergencyPharmacyItem::create([
                ...$data,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->movement($item, (int) $item->quantity, 0, (int) $item->quantity, $actor, 'stock_added', 'Opening A&E stock.');

            return $item;
        });
    }

    /**
     * Increase stock and append its movement under a row lock.
     *
     * @param string|null $notes Optional audit note attached to the restock movement.
     */
    public function restock(EmergencyPharmacyItem $item, int $quantity, User $actor, ?string $notes = null): void
    {
        abort_unless($actor->canManageEmergencyPharmacy(), 403);

        DB::transaction(function () use ($item, $quantity, $actor, $notes) {
            $item = EmergencyPharmacyItem::query()->lockForUpdate()->findOrFail($item->id);
            $before = (int) $item->quantity;
            $item->quantity += $quantity;
            $item->updated_by = $actor->id;
            $item->save();
            $this->movement($item, $quantity, $before, (int) $item->quantity, $actor, 'restock', $notes);
        });
    }

    /**
     * Update A&E catalogue details and log a zero-quantity movement when its price changes.
     *
     * @param array<string, mixed> $data Validated editable item fields, including unit price.
     */
    public function updateItem(EmergencyPharmacyItem $item, array $data, User $actor): void
    {
        abort_unless($actor->canManageEmergencyPharmacy(), 403);

        DB::transaction(function () use ($item, $data, $actor) {
            $item = EmergencyPharmacyItem::query()->lockForUpdate()->findOrFail($item->id);
            $before = (int) $item->quantity;
            $priceChanged = (float) $item->unit_price !== (float) $data['unit_price'];
            $item->fill($data);
            $item->updated_by = $actor->id;
            $item->save();
            if ($priceChanged) {
                $this->movement($item, 0, $before, $before, $actor, 'price_updated', 'A&E local selling price updated.');
            }
        });
    }

    /** Enable or disable an A&E catalogue item without deleting its stock or movement history. */
    public function setActive(EmergencyPharmacyItem $item, bool $active, User $actor): void
    {
        abort_unless($actor->canManageEmergencyPharmacy(), 403);
        $item->update(['is_active' => $active, 'updated_by' => $actor->id]);
    }

    /**
     * Consume A&E stock for an active emergency visit and create its invoice line atomically.
     *
     * A&E-accessible staff and administrators may record use; inactive items and insufficient
     * stock are rejected, and the item's current unit price is snapshotted on the use record.
     *
     * @param array<string, mixed> $data Validated item ID, quantity, and optional dose/note.
     */
    public function recordUse(Visit $visit, array $data, User $recorder): EmergencyItemUse
    {
        abort_unless($recorder->hasRole(Role::Admin) || $recorder->canAccessEmergency(), 403);
        abort_unless($visit->is_emergency && in_array($visit->status, [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation], true), 422, 'Items can only be recorded on an active A&E visit.');

        return DB::transaction(function () use ($visit, $data, $recorder) {
            $item = EmergencyPharmacyItem::query()->lockForUpdate()->findOrFail($data['emergency_pharmacy_item_id']);
            abort_unless($item->is_active, 422, 'This A&E catalogue item is inactive.');
            $quantity = (int) $data['quantity'];
            if ($item->quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => "Only {$item->quantity} {$item->unit}(s) of {$item->name} remain in A&E stock. For items not stocked by A&E, use the main Pharmacy prescription workflow."]);
            }

            $before = (int) $item->quantity;
            $item->quantity -= $quantity;
            $item->save();
            $use = EmergencyItemUse::create([
                'visit_id' => $visit->id,
                'emergency_pharmacy_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_price' => $item->unit_price,
                'dose_or_amount' => $data['dose_or_amount'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $recorder->id,
            ]);
            $this->movement($item, -$quantity, $before, (int) $item->quantity, $recorder, 'patient_use', "Used for A&E visit #{$visit->id}.", $visit);

            $invoice = $this->billing->invoiceFor($visit);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'category' => 'emergency_item',
                'description' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $use->unit_price,
                'billable_type' => $use->getMorphClass(),
                'billable_id' => $use->id,
            ]);

            return $use;
        });
    }

    /** Persist an inventory audit movement with the resulting balance and optional visit link. */
    private function movement(EmergencyPharmacyItem $item, int $change, int $before, int $after, User $actor, string $type, ?string $notes, ?Visit $visit = null): void
    {
        EmergencyPharmacyItemMovement::create([
            'emergency_pharmacy_item_id' => $item->id,
            'visit_id' => $visit?->id,
            'user_id' => $actor->id,
            'type' => $type,
            'quantity_change' => $change,
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => $notes,
        ]);
    }
}
