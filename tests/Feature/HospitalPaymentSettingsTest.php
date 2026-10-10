<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\HospitalSetting;
use App\Models\User;
use App\Services\PaystackGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HospitalPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_encrypted_paystack_credentials_and_gateway_uses_them(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/test'],
            ]),
        ]);

        $admin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $secretKey = 'sk_live_test_secret_value';

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'name' => 'Test Hospital',
                'registration_fee' => 2000,
                'paystack_public_key' => 'pk_live_test_public_value',
                'paystack_secret_key' => $secretKey,
            ])
            ->assertRedirect(route('settings.edit'));

        $storedSecret = DB::table('hospital_settings')->value('paystack_secret_key');

        $this->assertNotSame($secretKey, $storedSecret);
        $this->assertSame($secretKey, HospitalSetting::current()->paystack_secret_key);
        $this->get(route('settings.edit'))->assertDontSee($secretKey);

        $checkoutUrl = app(PaystackGateway::class)->initialize(
            email: 'patient@example.test',
            amountKobo: 200000,
            reference: 'HMS-TEST',
            callbackUrl: 'https://hospital.test/callback',
        );

        $this->assertSame('https://checkout.paystack.com/test', $checkoutUrl);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.$secretKey));
    }
}
