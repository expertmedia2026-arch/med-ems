<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Support\HospitalContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds pharmacy queues and audit data and coordinates stock dispensing/restocking.
 *
 * Pharmacy summaries explicitly constrain aggregate queries to the active hospital.
 * Dispensing runs inventory reservation and prescription updates transactionally.
 */
class PharmacyService
{
    /** @param DrugInventoryService $inventory Handles reservation expiry and stock movements. */
    public function __construct(private readonly DrugInventoryService $inventory) {}

    /**
     * Assemble the current pharmacy queue, stock alerts, and today's activity.
     *
     * Expired reservations are processed first. The returned queue includes only
     * eligible pending orders and completed prescriptions dispensed today.
     *
     * @return array<string, mixed> Dashboard data for pharmacy queue, stock, summary, and pharmacist activity.
     */
    public function dashboardData(): array
    {
        $this->inventory->expireReservations();
        $startOfToday = CarbonImmutable::today();
        $endOfToday = CarbonImmutable::today()->endOfDay();
        $today = $this->summaryForRange($startOfToday, $endOfToday);
        $dispensedToday = Prescription::with(['visit.patient', 'drug', 'dispenser'])->completed()
            ->whereDate('dispensed_at', today())->latest('dispensed_at')->limit(15)->get();

        return [
            'queue' => Visit::with([
                'patient',
                'prescriptions' => fn ($query) => $this->pendingAtPharmacy($query)->with(['drug', 'invoiceItem', 'stockReservation']),
            ])->where(fn ($query) => $query
                ->whereIn('status', [VisitStatus::Completed->value, VisitStatus::Inpatient->value])
                ->orWhere(fn ($emergency) => $emergency->where('is_emergency', true)->where('status', VisitStatus::InConsultation->value))
                ->orWhere(fn ($activeConsultation) => $activeConsultation
                    ->where('status', VisitStatus::InConsultation->value)
                    ->where('is_emergency', false)
                    ->whereHas('prescriptions', fn ($prescriptions) => $this->pendingAtPharmacy($prescriptions))))
                ->whereHas('prescriptions', fn ($query) => $this->pendingAtPharmacy($query))
                ->orderByRaw('COALESCE(closed_at, checked_in_at) asc')->get(),
            'lowStock' => Drug::lowStock()->orderBy('stock_quantity')->get(),
            'dispensedToday' => $dispensedToday,
            'pharmacySummary' => $today,
            'pharmacistActivity' => $this->pharmacistActivity($startOfToday, $endOfToday),
        ];
    }

    /**
     * Build a date-bounded dispensing audit and the corresponding aggregate summaries.
     *
     * Unsupported period names fall back to the current month. Aggregate statistics
     * are constrained by the active hospital context; dispensing rows are paginated.
     *
     * @param  string  $period  One of today, week, month, or all.
     * @return array<string, mixed> Selected period, labels, summary, pharmacist activity, and dispensing paginator.
     */
    public function auditData(string $period = 'month'): array
    {
        $period = in_array($period, ['today', 'week', 'month', 'all'], true) ? $period : 'month';
        [$start, $end] = $this->periodBounds($period);
        $query = $this->dispensedQuery($start, $end);

        return [
            'period' => $period,
            'periods' => ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'This month', 'all' => 'All time'],
            'rangeLabel' => match ($period) {
                'today' => 'Today · '.now()->format('d M Y'),
                'week' => $start->format('d M Y').' – '.$end->format('d M Y'),
                'month' => now()->format('F Y'),
                default => 'All recorded history',
            },
            'summary' => $this->summaryForRange($start, $end),
            'pharmacistActivity' => $this->pharmacistActivity($start, $end),
            'dispensings' => $query->with(['visit.patient', 'visit.doctor', 'visit.department', 'drug', 'dispenser'])
                ->latest('dispensed_at')->paginate(30)->withQueryString(),
        ];
    }

    /**
     * Build the base query for completed prescriptions dispensed within a date range.
     *
     * The supplied bounds are expanded to the start and end of their respective days.
     *
     * @param  CarbonImmutable|null  $start  Inclusive lower date bound, or null for no lower bound.
     * @param  CarbonImmutable|null  $end  Inclusive upper date bound, or null for no upper bound.
     * @return Builder<Prescription> Completed dispensing query.
     */
    private function dispensedQuery(?CarbonImmutable $start, ?CarbonImmutable $end): Builder
    {
        return Prescription::query()->completed()->whereNotNull('dispensed_at')
            ->when($start, fn (Builder $query) => $query->where('dispensed_at', '>=', $start->startOfDay()))
            ->when($end, fn (Builder $query) => $query->where('dispensed_at', '<=', $end->endOfDay()));
    }

    /**
     * Count dispensed items, distinct patients, injection-form items, and pharmacists.
     *
     * All prescription aggregates and joined records are constrained to the active
     * hospital, and results are limited to the requested date range.
     *
     * @param  CarbonImmutable|null  $start  Inclusive lower date bound, or null for all earlier records.
     * @param  CarbonImmutable|null  $end  Inclusive upper date bound, or null for all later records.
     * @return array{items: int, patients: int, injections: int, pharmacists: int} Range totals.
     */
    private function summaryForRange(?CarbonImmutable $start, ?CarbonImmutable $end): array
    {
        $query = DB::table('prescriptions')
            ->join('visits', 'visits.id', '=', 'prescriptions.visit_id')
            ->join('drugs', 'drugs.id', '=', 'prescriptions.drug_id')
            ->where('prescriptions.status', WorkStatus::Completed->value)
            ->where('prescriptions.hospital_id', app(HospitalContext::class)->id())
            ->whereColumn('prescriptions.hospital_id', 'visits.hospital_id')
            ->whereColumn('prescriptions.hospital_id', 'drugs.hospital_id')
            ->whereNotNull('prescriptions.dispensed_at')
            ->when($start, fn ($query) => $query->where('prescriptions.dispensed_at', '>=', $start->startOfDay()))
            ->when($end, fn ($query) => $query->where('prescriptions.dispensed_at', '<=', $end->endOfDay()));

        $row = (clone $query)->selectRaw('COUNT(prescriptions.id) as item_count')
            ->selectRaw('COUNT(DISTINCT visits.patient_id) as patient_count')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(drugs.form, '')) LIKE '%inject%' THEN 1 ELSE 0 END) as injection_count")
            ->first();

        $pharmacyDepartmentId = Department::where('name', Department::PHARMACY_NAME)->value('id');
        $activePharmacists = DB::table('prescriptions')
            ->join('users as dispensers', 'dispensers.id', '=', 'prescriptions.dispensed_by')
            ->where('prescriptions.status', WorkStatus::Completed->value)
            ->where('prescriptions.hospital_id', app(HospitalContext::class)->id())
            ->whereColumn('prescriptions.hospital_id', 'dispensers.hospital_id')
            ->whereNotNull('prescriptions.dispensed_at')
            ->whereIn('dispensers.role', [Role::Pharmacist->value, Role::ChiefPharmacist->value])
            ->where('dispensers.department_id', $pharmacyDepartmentId ?? 0)
            ->when($start, fn ($query) => $query->where('prescriptions.dispensed_at', '>=', $start->startOfDay()))
            ->when($end, fn ($query) => $query->where('prescriptions.dispensed_at', '<=', $end->endOfDay()))
            ->distinct('prescriptions.dispensed_by')->count('prescriptions.dispensed_by');

        return [
            'items' => (int) ($row->item_count ?? 0),
            'patients' => (int) ($row->patient_count ?? 0),
            'injections' => (int) ($row->injection_count ?? 0),
            'pharmacists' => (int) $activePharmacists,
        ];
    }

    /**
     * Return pharmacy-department pharmacists with their dispensing totals for a period.
     *
     * Counts use completed dispensing records from the active hospital, grouped by
     * dispenser; pharmacists without activity are included with zero totals.
     *
     * @param  CarbonImmutable|null  $start  Inclusive lower date bound.
     * @param  CarbonImmutable|null  $end  Inclusive upper date bound.
     * @return Collection<int, array{user: User, patients: int, items: int, injections: int}>
     */
    private function pharmacistActivity(?CarbonImmutable $start, ?CarbonImmutable $end)
    {
        $counts = DB::table('prescriptions')
            ->join('visits', 'visits.id', '=', 'prescriptions.visit_id')
            ->join('drugs', 'drugs.id', '=', 'prescriptions.drug_id')
            ->where('prescriptions.status', WorkStatus::Completed->value)
            ->where('prescriptions.hospital_id', app(HospitalContext::class)->id())
            ->whereColumn('prescriptions.hospital_id', 'visits.hospital_id')
            ->whereColumn('prescriptions.hospital_id', 'drugs.hospital_id')
            ->whereNotNull('prescriptions.dispensed_at')
            ->whereNotNull('prescriptions.dispensed_by')
            ->when($start, fn ($query) => $query->where('prescriptions.dispensed_at', '>=', $start->startOfDay()))
            ->when($end, fn ($query) => $query->where('prescriptions.dispensed_at', '<=', $end->endOfDay()))
            ->select('prescriptions.dispensed_by')
            ->selectRaw('COUNT(prescriptions.id) as item_count')
            ->selectRaw('COUNT(DISTINCT visits.patient_id) as patient_count')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(drugs.form, '')) LIKE '%inject%' THEN 1 ELSE 0 END) as injection_count")
            ->groupBy('prescriptions.dispensed_by')
            ->get()->keyBy('dispensed_by');

        $pharmacyDepartmentId = Department::where('name', Department::PHARMACY_NAME)->value('id');

        return User::query()->with('department')->whereIn('role', [Role::Pharmacist->value, Role::ChiefPharmacist->value])
            ->where('department_id', $pharmacyDepartmentId ?? 0)
            ->orderBy('name')->get()->map(function (User $user) use ($counts) {
                $stats = $counts->get($user->id);

                return [
                    'user' => $user,
                    'patients' => (int) ($stats?->patient_count ?? 0),
                    'items' => (int) ($stats?->item_count ?? 0),
                    'injections' => (int) ($stats?->injection_count ?? 0),
                ];
            });
    }

    /**
     * Convert a named audit period into its inclusive start and end date bounds.
     *
     * @param  string  $period  Normalized audit period name.
     * @return array{?CarbonImmutable, ?CarbonImmutable} Start and end dates; both null for all-time history.
     */
    private function periodBounds(string $period): array
    {
        return match ($period) {
            'today' => [CarbonImmutable::today(), CarbonImmutable::today()],
            'week' => [CarbonImmutable::today()->subDays(6), CarbonImmutable::today()],
            'month' => [CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()],
            default => [null, null],
        };
    }

    /**
     * Load a pharmacy-eligible visit and its prescriptions, restoring valid reservations.
     *
     * Closed deceased visits are not accessible. Pending items that are paid or
     * belong to an emergency visit have their stock reservations reacquired.
     *
     * @param  Visit  $visit  Visit requested by the pharmacy workflow.
     * @return Visit Visit with patient, doctor, and eligible prescriptions loaded.
     */
    public function visitDetails(Visit $visit): Visit
    {
        abort_unless($this->canOpenVisit($visit), 404);

        $this->inventory->expireReservations();
        $visit->load(['prescriptions' => fn ($query) => $query
            ->where(fn ($visible) => $visible->where('status', WorkStatus::Completed->value)
                ->orWhere(fn ($pending) => $this->pendingAtPharmacy($pending)))
            ->with(['drug', 'dispenser', 'invoiceItem', 'stockReservation'])]);
        foreach ($visit->prescriptions as $prescription) {
            if ($prescription->status === WorkStatus::Pending && ($prescription->is_paid || $visit->is_emergency)) {
                $this->inventory->ensurePrescriptionReserved($prescription, null);
            }
        }

        return $visit->load(['patient', 'doctor']);
    }

    /**
     * Search the active drug catalog and optionally restrict results to low stock.
     *
     * Expired reservations are processed before availability is displayed.
     *
     * @param  string|null  $term  Optional name or generic-name search text.
     * @param  bool  $lowStock  Whether to include only drugs below the low-stock threshold.
     * @return LengthAwarePaginator Paginated matching drugs with query parameters retained.
     */
    public function inventoryData(?string $term, bool $lowStock, bool $includeBatches = false)
    {
        $this->inventory->expireReservations();

        return Drug::query()->when($includeBatches, fn ($query) => $query->with(['stockBatches' => fn ($batches) => $batches->where('quantity_remaining', '>', 0)->orderBy('expiry_date')]))
            ->when($term, fn ($query) => $query->where(fn ($search) => $search
                ->whereLike('name', "%{$term}%")->orWhereLike('generic_name', "%{$term}%")))
            ->when($lowStock, fn ($query) => $query->lowStock())->orderBy('name')->paginate(15)->withQueryString();
    }

    /** @return array{dispensed: int, skipped: array<int, string>} */
    /**
     * Dispense selected pharmacy orders and report orders that could not be completed.
     *
     * The visit must be open to the pharmacy. Each selected pending order is checked
     * for payment (except emergency visits), stock reservation, and fulfillment inside
     * one transaction. Nurses are notified after commit when in-facility medication
     * administration becomes ready.
     *
     * @param  Visit  $visit  Visit whose pharmacy queue is being processed.
     * @param  array<int, int>  $prescriptionIds  Prescription IDs requested for dispensing.
     * @param  int  $userId  Staff user recording the dispensing.
     * @return array{dispensed: int, nursing_ready: int, skipped: array<int, string>, has_pending: bool, patient_name: string} Dispensing outcome and remaining queue state.
     */
    public function dispense(Visit $visit, array $prescriptionIds, int $userId): array
    {
        abort_unless($this->canOpenVisit($visit), 403);
        $dispensed = 0;
        $nursingReady = 0;
        $skipped = [];

        DB::transaction(function () use ($visit, $prescriptionIds, $userId, &$dispensed, &$nursingReady, &$skipped) {
            $items = $this->pendingAtPharmacy($visit->prescriptions())->with(['invoiceItem', 'drug', 'stockReservation'])
                ->whereIn('id', array_unique($prescriptionIds))->orderBy('drug_id')->get();

            foreach ($items as $prescription) {
                if (! $prescription->is_paid && ! $visit->is_emergency) {
                    $skipped[] = "{$prescription->drug->name} (not paid)";

                    continue;
                }

                if (! $this->inventory->ensurePrescriptionReserved($prescription, null)) {
                    $drug = Drug::findOrFail($prescription->drug_id);
                    $skipped[] = "{$drug->name} (reserved stock expired and only {$drug->available_quantity} is now available)";

                    continue;
                }

                if (! $this->inventory->fulfillPrescription($prescription, $userId)) {
                    $skipped[] = "{$prescription->drug->name} (reserved stock is no longer available)";

                    continue;
                }

                $prescription->update([
                    'status' => WorkStatus::Completed,
                    'dispensed_by' => $userId,
                    'dispensed_at' => now(),
                ]);
                $dispensed++;
                $nursingReady += (int) $prescription->requires_in_facility_administration;
            }
        });

        if ($nursingReady > 0) {
            $visit->loadMissing('patient');
            app(WorkflowNotificationService::class)->medicationNursesFor(
                $visit,
                'Medication ready for administration',
                "Pharmacy dispensed {$nursingReady} hospital medication order(s) for {$visit->patient->full_name} ({$visit->patient->hospital_number}). The nursing administration task is ready.",
            );
        }

        return [
            'dispensed' => $dispensed,
            'nursing_ready' => $nursingReady,
            'skipped' => $skipped,
            'has_pending' => $this->pendingAtPharmacy($visit->prescriptions())->exists(),
            'patient_name' => $visit->patient->full_name,
        ];
    }

    /**
     * Add stock to a drug through the inventory service.
     *
     * @param  Drug  $drug  Drug receiving additional stock.
     * @param  int  $quantity  Positive quantity to add.
     * @param  int  $userId  Staff user recording the restock.
     * @return Drug Updated drug record.
     */
    public function restock(Drug $drug, int $quantity, int $userId, ?array $batch = null): Drug
    {
        return $this->inventory->restock($drug, $quantity, $userId, $batch);
    }

    /**
     * Constrain a prescription query to pending orders visible in pharmacy.
     *
     * Orders must be sent to pharmacy or belong to an eligible completed, inpatient,
     * or emergency visit, and are hidden when the visit disposition is deceased.
     *
     * @param  HasMany|Builder  $query  Prescription query to constrain.
     * @return HasMany|Builder Filtered pending-order query.
     */
    private function pendingAtPharmacy($query)
    {
        return $query->pending()->where(fn ($visible) => $visible
            ->whereNotNull('pharmacy_ordered_at')
            ->orWhereHas('visit', fn ($visit) => $visit
                ->whereIn('status', [VisitStatus::Completed->value, VisitStatus::Inpatient->value])
                ->orWhere('is_emergency', true)))
            ->whereHas('visit', fn ($visit) => $visit->where(fn ($open) => $open
                ->whereNull('disposition')
                ->orWhere('disposition', '!=', 'deceased')));
    }

    /**
     * Decide whether the given visit can be opened from the pharmacy workflow.
     *
     * Deceased visits are always excluded; other visits are eligible when completed,
     * inpatient, emergency consultations, or consultations with pharmacy-pending orders.
     *
     * @param  Visit  $visit  Visit whose pharmacy eligibility is checked.
     * @return bool Whether pharmacy staff may open this visit.
     */
    private function canOpenVisit(Visit $visit): bool
    {
        if ($visit->disposition === 'deceased') {
            return false;
        }

        return in_array($visit->status, [VisitStatus::Completed, VisitStatus::Inpatient], true)
            || ($visit->is_emergency && $visit->status === VisitStatus::InConsultation)
            || ($visit->status === VisitStatus::InConsultation
                && $this->pendingAtPharmacy($visit->prescriptions())->exists());
    }
}
