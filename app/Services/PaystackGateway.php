<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway
{
    /** Start a payment and return the Paystack page the patient should be sent to. */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = []): string
    {
        $response = $this->http()->post('/transaction/initialize', [
            'email'        => $email,
            'amount'       => $amountKobo,
            'currency'     => 'NGN',
            'reference'    => $reference,
            'callback_url' => $callbackUrl,
            'metadata'     => $metadata,
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack initialize failed: ' . $response->body());
        }

        return $response->json('data.authorization_url');
    }

    /** Ask Paystack what really happened to a payment. */
    public function verify(string $reference): array
    {
        $response = $this->http()->get('/transaction/verify/' . urlencode($reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack verify failed: ' . $response->body());
        }

        $data = $response->json('data');

        return [
            'status'      => $data['status'] ?? 'unknown',
            'amount_kobo' => (int) ($data['amount'] ?? 0),
            'currency'    => $data['currency'] ?? '',
            'reference'   => $data['reference'] ?? $reference,
        ];
    }

    /** Paystack signs every webhook body with the secret key. */
    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.paystack.secret_key');

        return $secret
            && $signature
            && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function http(): PendingRequest
    {
        $secret = config('services.paystack.secret_key');

        if (blank($secret)) {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not set.');
        }

        return Http::baseUrl(config('services.paystack.base_url'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout(20);
    }
}