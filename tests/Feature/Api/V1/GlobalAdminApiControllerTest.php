<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\User;
use App\Notifications\HospitalActionNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalAdminApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Platform admins can authenticate through the standard API login and reach platform endpoints. */
    public function test_global_admin_can_login_and_read_platform_dashboard(): void
    {
        $admin = $this->createGlobalAdmin();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
            'device_name' => 'API tests',
        ])->assertOk()
            ->assertJsonPath('data.user.email', $admin->email);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $admin->email);

        $this->withToken($token)
            ->getJson('/api/v1/platform/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['hospital_count', 'active_hospital_count', 'staff_count', 'patient_count'],
            ])
            ->assertJsonPath('data.hospital_count', Hospital::count());

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/logout')
            ->assertOk();
    }

    /** Hospital administrators must not access the platform-only endpoint group. */
    public function test_hospital_admin_is_forbidden_from_platform_endpoints(): void
    {
        $hospital = Hospital::create([
            'name' => 'Tenant Hospital',
            'slug' => 'tenant-hospital',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'hospital_id' => $hospital->id,
            'role' => Role::Admin,
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/hospitals')->assertForbidden();
    }

    /** Hospital onboarding creates the tenant, first admin, settings, modules, and workflow atomically. */
    public function test_global_admin_can_create_a_hospital_and_its_initial_configuration(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->createGlobalAdmin());

        $response = $this->postJson('/api/v1/platform/hospitals', [
            'name' => 'New Maternity Hospital',
            'admin_name' => 'Hospital Admin',
            'admin_email' => 'admin@new-maternity.test',
            'modules' => ['inpatient', 'maternity'],
            'admission_template' => 'current',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.hospital.name', 'New Maternity Hospital')
            ->assertJsonPath('data.hospital.enabled_modules', ['inpatient', 'maternity'])
            ->assertJsonPath('data.hospital.admission_template', 'current')
            ->assertJsonPath('data.admin_email', 'admin@new-maternity.test')
            ->assertJsonPath('data.setup_link_sent', true);

        $hospital = Hospital::query()->where('slug', 'new-maternity-hospital')->sole();
        $hospitalAdmin = User::query()->where('email', 'admin@new-maternity.test')->sole();

        $this->assertSame($hospital->id, $hospitalAdmin->hospital_id);
        $this->assertDatabaseHas('hospital_settings', [
            'hospital_id' => $hospital->id,
            'name' => 'New Maternity Hospital',
        ]);
        $this->assertDatabaseHas('hospital_modules', [
            'hospital_id' => $hospital->id,
            'module' => 'maternity',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('hospital_workflows', [
            'hospital_id' => $hospital->id,
            'workflow' => 'admission',
            'version' => 1,
            'is_active' => true,
        ]);
        Notification::assertSentTo($hospitalAdmin, ResetPassword::class);
    }

    /** Module prerequisites are checked before any hospital is created. */
    public function test_module_dependencies_are_validated_during_hospital_onboarding(): void
    {
        Sanctum::actingAs($this->createGlobalAdmin());

        $this->postJson('/api/v1/platform/hospitals', [
            'name' => 'Invalid Module Hospital',
            'admin_name' => 'Hospital Admin',
            'admin_email' => 'admin@invalid-modules.test',
            'modules' => ['maternity'],
            'admission_template' => 'basic',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['modules']);

        $this->assertDatabaseMissing('hospitals', ['slug' => 'invalid-module-hospital']);
    }

    /** Configuration updates publish a new workflow version and explicit status changes are repeatable. */
    public function test_global_admin_can_update_configuration_and_hospital_status(): void
    {
        $admin = $this->createGlobalAdmin();
        $hospital = Hospital::create([
            'name' => 'Existing Hospital',
            'slug' => 'existing-hospital',
            'is_active' => true,
        ]);
        HospitalSetting::create(['hospital_id' => $hospital->id, 'name' => $hospital->name]);
        HospitalWorkflow::create([
            'hospital_id' => $hospital->id,
            'workflow' => 'admission',
            'version' => 1,
            'definition' => ['template' => 'basic', 'sections' => [], 'custom_fields' => []],
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/platform/hospitals/{$hospital->id}/configuration", [
            'modules' => ['inpatient', 'maternity'],
            'admission_template' => 'current',
        ])->assertOk()
            ->assertJsonPath('data.enabled_modules', ['inpatient', 'maternity'])
            ->assertJsonPath('data.admission_template', 'current');

        $this->patchJson("/api/v1/platform/hospitals/{$hospital->id}/status", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('hospital_workflows', [
            'hospital_id' => $hospital->id,
            'version' => 2,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('hospitals', [
            'id' => $hospital->id,
            'is_active' => false,
        ]);
    }

    /** Platform administrators can manage other platform accounts without modifying tenant users. */
    public function test_global_admin_can_create_and_deactivate_another_platform_admin(): void
    {
        Sanctum::actingAs($this->createGlobalAdmin());

        $created = $this->postJson('/api/v1/platform/admins', [
            'name' => 'Second Global Admin',
            'email' => 'second-global-admin@test.local',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'second-global-admin@test.local')
            ->assertJsonPath('data.role', 'admin');

        $userId = $created->json('data.id');

        $this->patchJson("/api/v1/platform/admins/{$userId}/status", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'hospital_id' => null,
            'is_global_admin' => true,
            'is_active' => false,
        ]);
    }

    /** The notification API exposes only the caller's notifications and supports read acknowledgements. */
    public function test_global_admin_can_list_and_mark_notifications_read(): void
    {
        $admin = $this->createGlobalAdmin();
        $admin->notify(new HospitalActionNotification(
            'Hospital name updated',
            'A hospital changed its name.',
            route('global-admin.dashboard'),
        ));
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/platform/notifications?unread_only=true')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.title', 'Hospital name updated');

        $notificationId = $response->json('data.items.0.id');
        $this->patchJson("/api/v1/platform/notifications/{$notificationId}/read")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'notifiable_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    private function createGlobalAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Global Admin',
            'email' => 'global-admin@test.local',
            'password' => 'password123',
            'role' => Role::Admin,
            'hospital_id' => null,
            'department_id' => null,
            'is_global_admin' => true,
            'is_active' => true,
        ]);
    }
}
