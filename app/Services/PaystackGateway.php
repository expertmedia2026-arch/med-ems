<?php

namespace App\Services;

use App\Models\HospitalSetting;
use App\Support\HospitalContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway
{
    /** Start a payment and return the Paystack page the patient should be sent to. */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = []): string
    {
        $response = $this->http()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amountKobo,
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack initialize failed: '.$response->body());
        }

        return $response->json('data.authorization_url');
    }

    /** Ask Paystack what really happened to a payment. */
    public function verify(string $reference): array
    {
        $response = $this->http()->get('/transaction/verify/'.urlencode($reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack verify failed: '.$response->body());
        }

        $data = $response->json('data');

        return [
            'status' => $data['status'] ?? 'unknown',
            'amount_kobo' => (int) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? '',
            'reference' => $data['reference'] ?? $reference,
        ];
    }

    /** Paystack signs every webhook body with the secret key. */
    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->secretKey();

        return $secret
            && $signature
            && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function http(): PendingRequest
    {
        $secret = $this->secretKey();

        if (blank($secret)) {
            throw new RuntimeException('Paystack secret key is not configured in Hospital Settings or the environment.');
        }

        return Http::baseUrl(config('services.paystack.base_url'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout(20);
    }

    public function publicKey(): ?string
    {
        return $this->currentHospitalSetting()?->paystack_public_key ?: config('services.paystack.public_key');
    }

    private function secretKey(): ?string
    {
        return $this->currentHospitalSetting()?->paystack_secret_key ?: config('services.paystack.secret_key');
    }

    private function currentHospitalSetting(): ?HospitalSetting
    {
        $hospitalId = app(HospitalContext::class)->id() ?? auth()->user()?->hospital_id;

        return $hospitalId ? HospitalSetting::current() : null;
    }
}
