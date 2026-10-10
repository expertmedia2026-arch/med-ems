<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoRegistrationPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $cashierId = User::where('email', 'cashier@hms.test')->value('id');
        $billing = app(BillingService::class);

        Patient::query()
            ->where(fn ($query) => $query
                ->where('phone', 'like', '080700%')
                ->orWhere('phone', 'like', '080800%'))
            ->orderBy('id')
            ->each(function (Patient $patient) use ($billing, $cashierId) {
                DB::transaction(function () use ($patient, $billing, $cashierId) {
                    $patient->update([
                        'registration_paid_at' => $patient->registration_paid_at ?? now(),
                    ]);

                    $invoice = $billing->registrationInvoiceFor($patient);

                    if (! $invoice) {
                        // Keep a paid zero-value registration invoice visible for free demo registrations.
                        $invoice = Invoice::firstOrCreate(
                            ['patient_id' => $patient->id, 'visit_id' => null],
                        );

                        if (! $invoice->items()->where('category', 'registration')->exists()) {
                            $invoice->items()->create([
                                'category' => 'registration',
                                'description' => 'Registration fee',
                                'quantity' => 1,
                                'unit_price' => 0,
                            ]);
                        }

                        return;
                    }

                    $unpaidItems = $invoice->items()
                        ->where('category', 'registration')
                        ->whereNull('payment_id')
                        ->get();
                    $amount = (float) $unpaidItems->sum('total');

                    if ($unpaidItems->isEmpty() || $amount <= 0) {
                        return;
                    }

                    if (! $cashierId) {
                        throw new \RuntimeException('Seed the demo cashier account before demo registration payments.');
                    }

                    $payment = $invoice->payments()->firstOrCreate(
                        ['reference' => 'SEED-REG-'.$patient->id],
                        [
                            'amount' => $amount,
                            'method' => 'cash',
                            'received_by' => $cashierId,
                        ],
                    );

                    $invoice->items()
                        ->whereIn('id', $unpaidItems->modelKeys())
                        ->whereNull('payment_id')
                        ->update(['payment_id' => $payment->id]);
                });
            });
    }
}
