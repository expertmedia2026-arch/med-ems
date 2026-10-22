<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Role;
use App\Models\HospitalSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->resource;
        $invoice = $payment->invoice;
        $patient = $invoice->patient;
        $hospital = HospitalSetting::current();
        $contacts = collect([$hospital->phone, $hospital->email, $hospital->website])
            ->filter()
            ->implode(' · ');
        $method = $payment->method === 'online' ? 'ONLINE' : strtoupper($payment->method);
        $isPatient = auth()->user()?->role === Role::Patient;
        $backUrl = $isPatient
            ? route('portal.invoices.show', $invoice)
            : route('billing.show', $invoice);

        return [
            'title' => 'Payment receipt',
            'hospital' => [
                'name' => $hospital->name,
                'logo_url' => $hospital->logo_url,
                'logo_fallback' => '✚',
                'address' => $hospital->address,
                'phone' => $hospital->phone,
                'email' => $hospital->email,
                'website' => $hospital->website,
                'contacts' => $contacts,
            ],
            'receipt_number' => $payment->receipt_number,
            'invoice_number' => $invoice->invoice_number,
            'patient' => [
                'full_name' => $patient->full_name,
                'hospital_number' => $patient->hospital_number,
            ],
            'created_at' => $payment->created_at->toIso8601String(),
            'date_label' => $payment->created_at->format('d M Y, g:i A'),
            'items' => $payment->items->map(fn ($item) => [
                'description' => $isPatient ? $item->descriptionForPatient() : $item->descriptionForBilling(),
                'quantity' => $item->category === 'drug' ? null : $item->quantity,
                'quantity_label' => $item->quantity > 1 && $item->category !== 'drug' ? '× '.$item->quantity : null,
                'amount' => (float) $item->total,
                'amount_label' => '₦'.number_format((float) $item->total, 2),
            ])->values(),
            'amount_paid' => (float) $payment->amount,
            'amount_paid_label' => '₦'.number_format((float) $payment->amount, 2),
            'method' => $payment->method,
            'method_label' => $method.($payment->reference ? ' · '.$payment->reference : ''),
            'reference' => $payment->reference,
            'received_by' => $payment->receiver?->name,
            'received_by_label' => $payment->receiver ? 'Received by '.$payment->receiver->name : 'Paid online',
            'footer_message' => $hospital->receipt_footer ?: 'Thank you. Get well soon.',
            'print_button_label' => 'Print',
            'back_to_invoice_url' => $backUrl,
            'back_to_invoice_label' => 'Back to invoice',
        ];
    }
}
