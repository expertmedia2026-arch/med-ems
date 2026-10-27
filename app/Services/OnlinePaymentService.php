<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OnlinePayment;
use App\Models\User;
use App\Notifications\HospitalActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnlinePaymentService
{
    public function __construct(
        private PaystackGateway $gateway,
        private DrugInventoryService $inventory,
    ) {}

    public function findForPatient(string $reference, int $patientId): ?OnlinePayment
    {
        return OnlinePayment::where('reference', $reference)
            ->where('patient_id', $patientId)
            ->first();
    }

    /** Record the attempt, ask the gateway for a checkout page, and return its URL. */
    public function start(Invoice $invoice, Collection $items, User $user): string
    {
        $amount = round((float) $items->sum('total'), 2);

        $attempt = OnlinePayment::create([
            'patient_id' => $invoice->patient_id,
            'invoice_id' => $invoice->id,
            'reference' => 'HMS'.now()->format('ymdHis').strtoupper(Str::random(6)),
            'amount' => $amount,
            'item_ids' => $items->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]);

        try {
            return $this->gateway->initialize(
                email: $user->email,
                amountKobo: (int) round($amount * 100),
                reference: $attempt->reference,
                callbackUrl: route('portal.payments.callback'),
                metadata: ['online_payment_id' => $attempt->id, 'invoice' => $invoice->invoice_number],
            );
        } catch (\Throwable $e) {
            $attempt->update(['status' => 'failed', 'note' => 'Could not reach the payment gateway.']);

            throw $e;
        }
    }

    /**
     * Check a payment with the gateway and, if it succeeded, record it against the bill.
     * Safe to call any number of times (callback, webhook, retries).
     */
    public function confirm(string $reference): ?OnlinePayment
    {
        $attempt = OnlinePayment::where('reference', $reference)->first();

        if (! $attempt) {
            return null;
        }

        if (in_array($attempt->status, ['success', 'review'], true)) {
            return $attempt;
        }

        $result = $this->gateway->verify($reference);

        if ($result['status'] !== 'success') {
            // Still in progress: leave it pending. Anything else means it did not go through.
            if (! in_array($result['status'], ['ongoing', 'pending', 'processing', 'queued'], true)) {
                $attempt->update(['status' => 'failed', 'note' => 'Gateway status: '.$result['status']]);
            }

            return $attempt->fresh();
        }

        // Paid, but is it the amount we asked for?
        $expected = (int) round((float) $attempt->amount * 100);

        if ($result['amount_kobo'] !== $expected || $result['currency'] !== 'NGN') {
            $attempt->update([
                'status' => 'review',
                'paid_at' => now(),
                'note' => 'Amount or currency did not match what was requested. Check the gateway dashboard.',
            ]);
            $this->notifyPaymentReview($attempt);

            return $attempt->fresh();
        }

        return DB::transaction(function () use ($attempt) {
            $attempt = OnlinePayment::lockForUpdate()->find($attempt->id);

            if (in_array($attempt->status, ['success', 'review'], true)) {
                return $attempt; // the webhook or the callback got here first
            }

            $invoice = Invoice::lockForUpdate()->find($attempt->invoice_id);

            $items = $invoice->items()
                ->whereIn('id', $attempt->item_ids)
                ->whereNull('payment_id')
                ->where('total', '>', 0)
                ->lockForUpdate()
                ->get();

            // Something was settled at the billing desk in the meantime: do not record twice
            if ($items->count() !== count($attempt->item_ids)) {
                $attempt->update([
                    'status' => 'review',
                    'paid_at' => now(),
                    'note' => 'Paid online, but some items were already settled at the billing desk. Refund or reconcile in the gateway dashboard.',
                ]);
                $this->notifyPaymentReview($attempt);

                return $attempt;
            }

            if (! $this->inventory->ensureItemsReserved($items, null)) {
                $attempt->update([
                    'status' => 'review',
                    'paid_at' => now(),
                    'note' => 'Payment succeeded online, but the reserved drug stock could not be reacquired. Refund or reconcile in the gateway dashboard.',
                ]);
                $this->notifyPaymentReview($attempt);

                return $attempt;
            }

            $payment = $invoice->payments()->create([
                'amount' => $attempt->amount,
                'method' => 'online',
                'reference' => $attempt->reference,
                'received_by' => null,
            ]);

            InvoiceItem::whereIn('id', $items->pluck('id'))->update(['payment_id' => $payment->id]);

            // Paying the registration fee activates the portal login
            if ($items->contains('category', 'registration')) {
                $invoice->patient()->whereNull('registration_paid_at')->update(['registration_paid_at' => now()]);
            }

            $attempt->update([
                'status' => 'success',
                'paid_at' => now(),
                'payment_id' => $payment->id,
                'note' => null,
            ]);

            $attempt->loadMissing('patient.user');
            $patientUser = $attempt->patient?->user;
            if ($patientUser && $patientUser->hospital?->hasModule('patient_portal')) {
                $patientUser->notify(new HospitalActionNotification(
                    'Online payment confirmed',
                    'Your online payment of ₦'.number_format((float) $attempt->amount, 2).' has been confirmed.',
                    route('portal.invoices.show', $invoice),
                    'receipt',
                ));
            }

            return $attempt;
        });
    }

    /** Recent pending attempts on an invoice: the patient may have paid and closed the page. */
    public function refreshPending(Invoice $invoice): void
    {
        OnlinePayment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDay())
            ->get()
            ->each(function (OnlinePayment $attempt) {
                try {
                    $this->confirm($attempt->reference);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }

    private function notifyPaymentReview(OnlinePayment $attempt): void
    {
        User::query()->where('is_active', true)
            ->whereIn('role', ['admin', 'accountant', 'chief_accountant'])
            ->get()->each(fn (User $user) => $user->notify(new HospitalActionNotification(
                'Online payment needs review',
                'An online payment could not be applied automatically. Review the payment in Billing.',
                route('billing.index'),
                'receipt',
            )));
    }

    /** Is a fresh payment already open for any of these items? Prevents charging twice. */
    public function hasPendingOverlap(Invoice $invoice, Collection $items): bool
    {
        $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        return OnlinePayment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->get()
            ->contains(fn (OnlinePayment $attempt) => count(array_intersect($attempt->item_ids, $ids)) > 0);
    }
}
