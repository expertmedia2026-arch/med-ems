<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GlobalAdminSubAdminTest extends TestCase
{
    use RefreshDatabase;

    /** A super-admin can create a sub-admin account but cannot accidentally grant super-admin access. */
    public function test_super_admin_can_create_a_sub_admin(): void
    {
        Notification::fake();
        $superAdmin = $this->createPlatformAdmin(isSuperAdmin: true);

        $response = $this->actingAs($superAdmin)->post(route('global-admin.admins.store'), [
            'name' => 'Platform Sub Admin',
            'email' => 'platform-sub-admin@example.test',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ]);

        $response->assertRedirect(route('global-admin.admins.index'));
        $subAdmin = User::query()->where('email', 'platform-sub-admin@example.test')->sole();
        $this->assertTrue($subAdmin->isGlobalAdmin());
        $this->assertFalse($subAdmin->isPlatformSuperAdmin());

        $this->actingAs($superAdmin)
            ->get(route('global-admin.admins.index'))
            ->assertOk()
            ->assertSee('Platform Sub Admin')
            ->assertSee('Sub-admin');
    }

    /** A sub-admin can use hospital pages but cannot discover or mutate platform administrator accounts. */
    public function test_sub_admin_can_manage_hospitals_but_cannot_see_or_manage_admins(): void
    {
        $hospital = Hospital::create([
            'name' => 'Sub-admin Visible Hospital',
            'slug' => 'sub-admin-visible-hospital',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Hidden Hospital Admin',
            'email' => 'hidden-hospital-admin@example.test',
            'role' => Role::Admin,
            'hospital_id' => $hospital->id,
            'is_active' => true,
        ]);
        $subAdmin = $this->createPlatformAdmin(isSuperAdmin: false);

        $this->actingAs($subAdmin)
            ->get(route('global-admin.dashboard'))
            ->assertOk()
            ->assertSee('Sub-admin Visible Hospital')
            ->assertDontSee('hidden-hospital-admin@example.test')
            ->assertDontSee(route('global-admin.admins.index'));

        $this->get(route('global-admin.hospitals.index'))
            ->assertOk()
            ->assertSee('Sub-admin Visible Hospital')
            ->assertDontSee('hidden-hospital-admin@example.test');

        $this->get(route('global-admin.admins.index'))->assertForbidden();
        $this->post(route('global-admin.admins.store'), [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized-admin@example.test',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-admin@example.test']);
    }

    public function test_super_admin_can_assign_a_hospital_admin_after_sub_admin_onboarding(): void
    {
        Notification::fake();
        $subAdmin = $this->createPlatformAdmin(isSuperAdmin: false);

        $this->actingAs($subAdmin)
            ->get(route('global-admin.hospitals.create'))
            ->assertOk()
            ->assertDontSee('name="admin_name"', false)
            ->assertSee('created without an administrator account');

        $this->post(route('global-admin.hospitals.store'), [
            'name' => 'Admin Injection Attempt',
            'admin_name' => 'Unauthorized Hospital Admin',
            'admin_email' => 'unauthorized-hospital-admin@example.test',
            'modules' => ['inpatient'],
            'admission_template' => 'basic',
        ])->assertSessionHasErrors(['admin_name', 'admin_email']);

        $this->assertDatabaseMissing('hospitals', ['slug' => 'admin-injection-attempt']);

        $this->post(route('global-admin.hospitals.store'), [
            'name' => 'Adminless Hospital',
            'modules' => ['inpatient'],
            'admission_template' => 'basic',
        ])->assertRedirect(route('global-admin.hospitals.index'));

        $hospital = Hospital::query()->where('slug', 'adminless-hospital')->sole();
        $this->assertDatabaseMissing('users', [
            'hospital_id' => $hospital->id,
            'role' => Role::Admin->value,
        ]);

        $this->post(route('global-admin.hospitals.admins.store', $hospital), [
            'name' => 'Unauthorized Hospital Admin',
            'email' => 'unauthorized-hospital-admin@example.test',
        ])->assertForbidden();

        $superAdmin = $this->createPlatformAdmin(isSuperAdmin: true);
        $this->actingAs($superAdmin)
            ->get(route('global-admin.hospitals.edit', $hospital))
            ->assertOk()
            ->assertSee('This hospital has no active administrator account yet.');

        $this->post(route('global-admin.hospitals.admins.store', $hospital), [
            'name' => 'Hospital Administrator',
            'email' => 'hospital-admin@example.test',
        ])->assertRedirect(route('global-admin.hospitals.edit', $hospital));

        $hospitalAdmin = User::query()->where('email', 'hospital-admin@example.test')->sole();
        $this->assertSame($hospital->id, $hospitalAdmin->hospital_id);
        Notification::assertSentTo($hospitalAdmin, ResetPassword::class);
    }

    private function createPlatformAdmin(bool $isSuperAdmin): User
    {
        return User::factory()->create([
            'name' => $isSuperAdmin ? 'Platform Global Admin' : 'Platform Sub Admin Actor',
            'role' => Role::Admin,
            'hospital_id' => null,
            'department_id' => null,
            'is_global_admin' => true,
            'is_platform_super_admin' => $isSuperAdmin,
            'is_active' => true,
        ]);
    }
}
