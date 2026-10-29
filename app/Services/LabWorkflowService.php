<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\HospitalSetting;
use App\Models\LabTest;
use App\Models\LabRequest;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\HospitalActionNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Coordinates clinician test requests, laboratory processing, result access, and result delivery.
 *
 * Queries use the current tenant's normal model scopes; the service applies requester and lab
 * department restrictions and delegates invoice synchronization to the shared billing service.
 */
class LabWorkflowService
{
    /**
     * Use the shared billing service when adding or removing tests changes a visit's charges.
     */
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Add previously unrequested tests to the requesting doctor's active consultation.
     *
     * The visit is locked and checked inside a transaction. Duplicate test IDs and tests already
     * attached to the visit are ignored; new requests trigger lab-staff notifications and invoice
     * synchronization. Returns the number of requests actually created.
     *
     * @param  array<int, int>  $testIds
     */
    public function requestTests(Visit $visit, array $testIds, int $requestedBy): int
    {
        return DB::transaction(function () use ($visit, $testIds, $requestedBy) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            abort_unless($visit->status === VisitStatus::InConsultation && $visit->doctor_id === $requestedBy, 403, 'Lab tests can only be added during your active consultation.');

            $already = $visit->labRequests()->pluck('lab_test_id');
            $new = collect($testIds)->unique()->diff($already);

            foreach ($new as $testId) {
                $visit->labRequests()->create(['lab_test_id' => $testId, 'requested_by' => $requestedBy]);
            }

            if ($new->isNotEmpty()) {
                $departmentIds = LabTest::query()->whereIn('id', $new)->distinct()->pluck('department_id');
                app(WorkflowNotificationService::class)->labStaff(
                    (int) $visit->hospital_id,
                    $departmentIds,
                    'A clinician added tests to the laboratory queue.',
                );
            }

            $this->billing->syncFor($visit);

            return $new->count();
        });
    }

    /**
     * Return independently paginated pending and completed requests visible to this staff member.
     *
     * Doctors see requests they submitted, lab users see requests for their department, and the
     * optional search term is matched against patient identity fields.
     *
     * @return array{pending: \Illuminate\Contracts\Pagination\LengthAwarePaginator, completed: \Illuminate\Contracts\Pagination\LengthAwarePaginator, canProcess: bool, search: string}
     */
    public function indexData(User $user, string $term): array
    {
        $base = LabRequest::with(['visit.patient', 'test', 'requester'])
            ->when($user->hasRole(Role::Doctor), fn ($query) => $query->where('requested_by', $user->id))
            ->when($user->hasRole(Role::Lab), fn ($query) => $query->whereHas('test', fn ($test) => $test->where('department_id', $user->department_id ?? 0)))
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

    /**
     * Load one request's detail and derive whether it can be processed or cancelled.
     *
     * Doctors may inspect only requests they made; lab users are restricted by the test department.
     * Cancellation is offered only for an uncompleted lab-only visit with no payments.
     *
     * @return array{labRequest: LabRequest, canProcess: bool, canCancel: bool}
     */
    public function detailData(LabRequest $labRequest, User $user): array
    {
        abort_if($user->hasRole(Role::Doctor) && $labRequest->requested_by !== $user->id, 403, 'You can only view results for tests you requested.');
        $labRequest->load(['visit.patient', 'test', 'requester', 'processor', 'attachments', 'invoiceItem']);
        $this->authorizeDepartment($labRequest, $user);
        $canProcess = $user->hasRole(Role::Admin, Role::Lab);
        $visit = $labRequest->visit;
        $canCancel = $canProcess && $visit->isLabOnly()
            && ! $visit->invoice?->payments()->exists() && ! $visit->labRequests()->completed()->exists();

        return compact('labRequest', 'canProcess', 'canCancel');
    }

    /**
     * Prepare completed results for printing for a visit, scoped to the requesting doctor or lab department.
     *
     * Throws a not-found response when no completed result is visible, and loads the patient and
     * attending doctor for the report.
     *
     * @return array{visit: Visit, requests: \Illuminate\Database\Eloquent\Collection<int, LabRequest>}
     */
    public function reportData(Visit $visit, User $user): array
    {
        $requests = $visit->labRequests()->completed()
            ->when($user->hasRole(Role::Doctor) && ! $visit->isLabOnly(), fn ($query) => $query->where('requested_by', $user->id))
            ->when($user->hasRole(Role::Lab), fn ($query) => $query->whereHas('test', fn ($test) => $test->where('department_id', $user->department_id ?? 0)))
            ->with(['test', 'attachments', 'processor'])->orderBy('id')->get();

        abort_if($requests->isEmpty(), 404, 'There are no completed results to print for this visit.');
        $visit->load(['patient', 'doctor']);

        return compact('visit', 'requests');
    }

    /**
     * Remove a pending test request from an open consultation and release its reserved charge.
     *
     * The visit and request are locked in one transaction. Returns false if billing cannot release
     * the request; otherwise the pending request is deleted.
     */
    public function removeRequest(LabRequest $request, User $doctor): bool
    {
        return DB::transaction(function () use ($request, $doctor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($request->visit_id);
            abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');

            $request = LabRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === WorkStatus::Pending, 422);

            if (! $this->billing->release($request)) {
                return false;
            }

            $request->delete();

            return true;
        });
    }

    /**
     * Save or replace a lab result and its private attachments after department and payment checks.
     *
     * First-time completion notifies the requester and eligible patient-portal account. A completed
     * lab-only visit is closed when no pending tests remain. If the patient has an email, a result
     * email is attempted; a delivery exception is reported and returned as a status string.
     *
     * @param  array<string, mixed>  $data  Validated result text and other result fields.
     * @param  array<int, UploadedFile>  $files  Uploaded result documents stored on the private local disk.
     * @return 'locked'|'unpaid'|'notification_failed'|'notification_missing_email'|null A blocking/delivery status, or null when processing completed.
     */
    public function saveResult(LabRequest $labRequest, array $data, array $files, User $processor): ?string
    {
        $wasCompleted = $labRequest->status === WorkStatus::Completed;
        $labRequest->loadMissing('test');
        $this->authorizeDepartment($labRequest, $processor);

        if ($labRequest->status === WorkStatus::Completed && $labRequest->visit->status === VisitStatus::Completed) {
            return 'locked';
        }

        $labRequest->loadMissing(['invoiceItem', 'visit']);
        if (! $labRequest->is_paid && ! $labRequest->visit->is_emergency) {
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
            'result_reviewed_by' => null,
            'result_reviewed_at' => null,
            'result_review_note' => null,
        ]);

        if (! $wasCompleted) {
            $labRequest->loadMissing(['requester', 'visit.patient.user']);
            $labRequest->requester?->notify(new HospitalActionNotification(
                'Lab result ready',
                'A requested laboratory result is ready for review.',
                route('lab.show', $labRequest),
                'flask',
            ));

            $patientUser = $labRequest->visit->patient?->user;
            if ($patientUser && $patientUser->hospital?->hasModule('patient_portal')) {
                $patientUser->notify(new HospitalActionNotification(
                    'Lab result ready',
                    'A new laboratory result is available in your patient portal.',
                    route('portal.visits.lab-report', $labRequest->visit),
                    'flask',
                ));
            }
        }

        $visit = $labRequest->visit;
        if ($visit->isLabOnly() && ! $visit->labRequests()->pending()->exists()) {
            $visit->update(['status' => VisitStatus::Completed, 'closed_at' => now()]);
            $visit->appointment?->update(['status' => AppointmentStatus::Completed]);
        }

        $patient = $visit->patient;
        $isAdmissionRelated = $visit->admission()->exists();
        if ($isAdmissionRelated) {
            return 'admission_review';
        }

        if ($patient->email) {
            try {
                $labRequest->loadMissing(['test', 'attachments', 'visit.patient']);
                Mail::to($patient->email)->send(new \App\Mail\LabResultMail(
                    $labRequest,
                    HospitalSetting::current()->name,
                    (bool) $visit->email_lab_results,
                ));
            } catch (\Throwable $exception) {
                report($exception);

                return 'notification_failed';
            }
        } elseif (! $isInpatient) {
            return 'notification_missing_email';
        }

        return null;
    }

    /**
     * Allow administrators/doctors, or laboratory users assigned to the test's department, to proceed.
     */
    private function authorizeDepartment(LabRequest $labRequest, User $user): void
    {
        abort_unless($user->hasRole(Role::Admin, Role::Doctor) || ($user->hasRole(Role::Lab)
            && $user->department_id !== null
            && (int) $user->department_id === (int) $labRequest->test?->department_id), 403);
    }
}
