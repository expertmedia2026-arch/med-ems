<?php

namespace App\Services;

use App\Models\HospitalSetting;
use App\Support\HospitalContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Makes authenticated Paystack API requests using hospital-specific credentials.
 *
 * Credentials are read from the current hospital settings when a hospital context
 * is available, with environment configuration as a fallback.
 */
class PaystackGateway
{
    /**
     * Initialize a Paystack transaction and return the hosted checkout URL.
     *
     * Amounts are submitted in kobo and the transaction currency is NGN. A failed
     * HTTP response or unsuccessful Paystack response raises RuntimeException.
     *
     * @param  string  $email  Payer email sent to Paystack.
     * @param  int  $amountKobo  Transaction amount in the gateway's minor currency unit.
     * @param  string  $reference  Unique local payment reference.
     * @param  string  $callbackUrl  URL Paystack should return the payer to.
     * @param  array<string, mixed>  $metadata  Optional transaction metadata.
     * @return string Hosted Paystack authorization URL.
     */
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

    /**
     * Verify a payment reference with Paystack and normalize the response fields.
     *
     * @param  string  $reference  Reference previously submitted to Paystack.
     * @return array{status: string, amount_kobo: int, currency: string, reference: string} Verified gateway status and payment details.
     *
     * @throws RuntimeException When Paystack rejects the request or returns an unsuccessful response.
     */
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

    /**
     * Compare a Paystack webhook signature against the raw request payload.
     *
     * Uses an HMAC-SHA512 digest and a timing-safe comparison. Returns false when
     * either the configured secret or supplied signature is absent.
     *
     * @param  string  $payload  Exact raw webhook request body.
     * @param  string|null  $signature  Signature header supplied by Paystack.
     * @return bool Whether the signature matches the configured secret.
     */
    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->secretKey();

        return $secret
            && $signature
            && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    /**
     * Build the authenticated, JSON-accepting Paystack HTTP client.
     *
     * The client has a 20-second timeout and requires a configured secret key.
     *
     * @return PendingRequest Configured Laravel HTTP client.
     *
     * @throws RuntimeException When no secret key is available.
     */
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

    /**
     * Return the current hospital's public key, falling back to environment config.
     *
     * @return string|null Public Paystack key, if configured.
     */
    public function publicKey(): ?string
    {
        return $this->currentHospitalSetting()?->paystack_public_key ?: config('services.paystack.public_key');
    }

    /**
     * Resolve the secret key for the current hospital, with environment fallback.
     *
     * @return string|null Secret Paystack key, if configured.
     */
    private function secretKey(): ?string
    {
        return $this->currentHospitalSetting()?->paystack_secret_key ?: config('services.paystack.secret_key');
    }

    /**
     * Load the current hospital's payment settings when a hospital can be identified.
     *
     * The hospital ID comes from the explicit hospital context or the authenticated
     * user's hospital; without either, no hospital setting is queried.
     *
     * @return HospitalSetting|null Current hospital settings, or null without a hospital context.
     */
    private function currentHospitalSetting(): ?HospitalSetting
    {
        $hospitalId = app(HospitalContext::class)->id() ?? auth()->user()?->hospital_id;

        return $hospitalId ? HospitalSetting::current() : null;
    }
}
