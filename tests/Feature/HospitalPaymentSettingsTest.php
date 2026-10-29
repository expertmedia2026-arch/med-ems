<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Hospital;
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

    public function test_hospital_name_changes_update_the_platform_hospital_and_notify_global_admins(): void
    {
        $hospital = Hospital::create([
            'name' => 'Original Hospital',
            'slug' => 'original-hospital',
            'is_active' => true,
        ]);
        $hospitalAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $hospitalAdmin->hospital()->associate($hospital);
        $hospitalAdmin->save();
        HospitalSetting::create([
            'hospital_id' => $hospital->id,
            'name' => 'Original Hospital',
        ]);

        $globalAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $globalAdmin->is_global_admin = true;
        $globalAdmin->save();

        $this->actingAs($hospitalAdmin)
            ->put(route('settings.update'), ['name' => 'Updated Hospital'])
            ->assertRedirect(route('settings.edit'));

        $this->assertDatabaseHas('hospitals', [
            'id' => $hospital->id,
            'name' => 'Updated Hospital',
        ]);
        $this->assertDatabaseHas('hospital_settings', [
            'hospital_id' => $hospital->id,
            'name' => 'Updated Hospital',
        ]);

        $notification = $globalAdmin->notifications()->firstOrFail();
        $this->assertSame('Hospital name updated', $notification->data['title']);
        $this->assertSame('The hospital name changed from Original Hospital to Updated Hospital.', $notification->data['message']);
        $this->assertSame(route('global-admin.hospitals.edit', $hospital), $notification->data['url']);

        $this->actingAs($globalAdmin)
            ->get(route('global-admin.dashboard'))
            ->assertOk()
            ->assertSee('Updated Hospital')
            ->assertSee(route('notifications.latest'), false);
    }

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
