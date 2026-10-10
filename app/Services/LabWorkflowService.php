<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\LabRequest;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;

class LabWorkflowService
{
    public function __construct(private readonly BillingService $billing) {}

    public function requestTests(Visit $visit, array $testIds, int $requestedBy): int
    {
        $already = $visit->labRequests()->pluck('lab_test_id');
        $new = collect($testIds)->unique()->diff($already);

        foreach ($new as $testId) {
            $visit->labRequests()->create(['lab_test_id' => $testId, 'requested_by' => $requestedBy]);
        }

        $this->billing->syncFor($visit);

        return $new->count();
    }

    public function indexData(User $user, string $term): array
    {
        $base = LabRequest::with(['visit.patient', 'test', 'requester'])
            ->when($user->hasRole(Role::Doctor), fn ($query) => $query->where('requested_by', $user->id))
            ->when($term !== '', function ($query) use ($term) {
                $query->whereHas('visit.patient', function ($patientQuery) use ($term) {
                    foreach (preg_split('/\s+/', $term) as $word) {
                        $patientQuery->where(function ($identityQuery) use ($word) {
                            $identityQuery->whereLike('hospital_number', "%{$word}%")
                                ->orWhereLike('phone', "%{$word}%")
                                ->orWhereLike('first_name', "%{$word}%")
                                ->orWhereLike('last_name', "%{$word}%");
                        });
                    }
                });
            });

        return [
            'pending' => (clone $base)->pending()->with('invoiceItem')->oldest()->paginate(10, ['*'], 'pending_page')->withQueryString(),
            'completed' => (clone $base)->completed()->with('processor')->withCount('attachments')->latest('completed_at')->paginate(10, ['*'], 'completed_page')->withQueryString(),
            'canProcess' => $user->hasRole(Role::Admin, Role::Lab),
            'search' => $term,
        ];
    }

    public function detailData(LabRequest $labRequest, User $user): array
    {
        abort_if($user->hasRole(Role::Doctor) && $labRequest->requested_by !== $user->id, 403, 'You can only view results for tests you requested.');
        $labRequest->load(['visit.patient', 'test', 'requester', 'processor', 'attachments', 'invoiceItem']);
        $canProcess = $user->hasRole(Role::Admin, Role::Lab);
        $visit = $labRequest->visit;
        $canCancel = $canProcess && $visit->isLabOnly()
            && ! $visit->invoice?->payments()->exists() && ! $visit->labRequests()->completed()->exists();

        return compact('labRequest', 'canProcess', 'canCancel');
    }

    public function reportData(Visit $visit, User $user): array
    {
        $requests = $visit->labRequests()->completed()
            ->when($user->hasRole(Role::Doctor), fn ($query) => $query->where('requested_by', $user->id))
            ->with(['test', 'attachments', 'processor'])->orderBy('id')->get();

        abort_if($requests->isEmpty(), 404, 'There are no completed results to print for this visit.');
        $visit->load(['patient', 'doctor']);

        return compact('visit', 'requests');
    }

    public function removeRequest(LabRequest $request): bool
    {
        abort_unless($request->status === WorkStatus::Pending, 422);

        if (! $this->billing->release($request)) {
            return false;
        }

        $request->delete();

        return true;
    }

    /** @param array<int, UploadedFile> $files */
    public function saveResult(LabRequest $labRequest, array $data, array $files, User $processor): ?string
    {
        if ($labRequest->status === WorkStatus::Completed && $labRequest->visit->status === VisitStatus::Completed) {
            return 'locked';
        }

        $labRequest->loadMissing('invoiceItem');
        if (! $labRequest->is_paid) {
            return 'unpaid';
        }

        foreach ($files as $file) {
            $labRequest->attachments()->create([
                'path' => $file->store("lab-attachments/{$labRequest->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $processor->id,
            ]);
        }

        $labRequest->update([
            'result' => $data['result'] ?? null,
            'status' => WorkStatus::Completed,
            'processed_by' => $processor->id,
            'completed_at' => $labRequest->completed_at ?? now(),
        ]);

        $visit = $labRequest->visit;
        if ($visit->isLabOnly() && ! $visit->labRequests()->pending()->exists()) {
            $visit->update(['status' => VisitStatus::Completed, 'closed_at' => now()]);
            $visit->appointment?->update(['status' => AppointmentStatus::Completed]);
        }

        return null;
    }
}
