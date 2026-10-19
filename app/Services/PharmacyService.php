<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PharmacyService
{
    public function __construct(private readonly DrugInventoryService $inventory) {}

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
                'prescriptions' => fn ($query) => $query->pending()->with(['drug', 'invoiceItem', 'stockReservation']),
            ])->withStatus(VisitStatus::Completed)
                ->whereHas('prescriptions', fn ($query) => $query->pending())->oldest('closed_at')->get(),
            'lowStock' => Drug::lowStock()->orderBy('stock_quantity')->get(),
            'dispensedToday' => $dispensedToday,
            'pharmacySummary' => $today,
            'pharmacistActivity' => $this->pharmacistActivity($startOfToday, $endOfToday),
        ];
    }

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

    private function dispensedQuery(?CarbonImmutable $start, ?CarbonImmutable $end): Builder
    {
        return Prescription::query()->completed()->whereNotNull('dispensed_at')
            ->when($start, fn (Builder $query) => $query->where('dispensed_at', '>=', $start->startOfDay()))
            ->when($end, fn (Builder $query) => $query->where('dispensed_at', '<=', $end->endOfDay()));
    }

    private function summaryForRange(?CarbonImmutable $start, ?CarbonImmutable $end): array
    {
        $query = DB::table('prescriptions')
            ->join('visits', 'visits.id', '=', 'prescriptions.visit_id')
            ->join('drugs', 'drugs.id', '=', 'prescriptions.drug_id')
            ->where('prescriptions.status', WorkStatus::Completed->value)
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

    private function pharmacistActivity(?CarbonImmutable $start, ?CarbonImmutable $end)
    {
        $counts = DB::table('prescriptions')
            ->join('visits', 'visits.id', '=', 'prescriptions.visit_id')
            ->join('drugs', 'drugs.id', '=', 'prescriptions.drug_id')
            ->where('prescriptions.status', WorkStatus::Completed->value)
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

    /** @return array{?CarbonImmutable, ?CarbonImmutable} */
    private function periodBounds(string $period): array
    {
        return match ($period) {
            'today' => [CarbonImmutable::today(), CarbonImmutable::today()],
            'week' => [CarbonImmutable::today()->subDays(6), CarbonImmutable::today()],
            'month' => [CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()],
            default => [null, null],
        };
    }

    public function visitDetails(Visit $visit): Visit
    {
        abort_unless($visit->status === VisitStatus::Completed, 404);

        $this->inventory->expireReservations();
        $visit->loadMissing(['prescriptions' => fn ($query) => $query->pending()->with('invoiceItem')]);
        foreach ($visit->prescriptions as $prescription) {
            if ($prescription->is_paid) {
                $this->inventory->ensurePrescriptionReserved($prescription, null);
            }
        }

        return $visit->load(['patient', 'doctor', 'prescriptions.drug', 'prescriptions.dispenser', 'prescriptions.invoiceItem', 'prescriptions.stockReservation']);
    }

    public function inventoryData(?string $term, bool $lowStock)
    {
        $this->inventory->expireReservations();

        return Drug::query()->when($term, fn ($query) => $query->where(fn ($search) => $search
            ->whereLike('name', "%{$term}%")->orWhereLike('generic_name', "%{$term}%")))
            ->when($lowStock, fn ($query) => $query->lowStock())->orderBy('name')->paginate(15)->withQueryString();
    }

    /** @return array{dispensed: int, skipped: array<int, string>} */
    public function dispense(Visit $visit, array $prescriptionIds, int $userId): array
    {
        abort_unless($visit->status === VisitStatus::Completed, 403);
        $dispensed = 0;
        $nursingReady = 0;
        $skipped = [];

        DB::transaction(function () use ($visit, $prescriptionIds, $userId, &$dispensed, &$nursingReady, &$skipped) {
            $items = $visit->prescriptions()->pending()->with(['invoiceItem', 'drug', 'stockReservation'])
                ->whereIn('id', array_unique($prescriptionIds))->orderBy('drug_id')->get();

            foreach ($items as $prescription) {
                if (! $prescription->is_paid) {
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

        return [
            'dispensed' => $dispensed,
            'nursing_ready' => $nursingReady,
            'skipped' => $skipped,
            'has_pending' => $visit->prescriptions()->pending()->exists(),
            'patient_name' => $visit->patient->full_name,
        ];
    }

    public function restock(Drug $drug, int $quantity, int $userId): Drug
    {
        return $this->inventory->restock($drug, $quantity, $userId);
    }
}
