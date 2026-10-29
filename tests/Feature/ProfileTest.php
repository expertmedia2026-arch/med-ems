<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_hospital_admin_email_changes_notify_global_admins_and_update_the_platform_summary(): void
    {
        $hospital = Hospital::create([
            'name' => 'Test Hospital',
            'slug' => 'test-hospital',
            'is_active' => true,
        ]);
        $hospitalAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $hospitalAdmin->hospital()->associate($hospital);
        $hospitalAdmin->save();

        $globalAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $globalAdmin->is_global_admin = true;
        $globalAdmin->save();
        $previousEmail = $hospitalAdmin->email;

        $this->actingAs($hospitalAdmin)
            ->patch(route('profile.update'), [
                'name' => $hospitalAdmin->name,
                'email' => 'updated-admin@example.test',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $hospitalAdmin->id,
            'email' => 'updated-admin@example.test',
        ]);

        $notification = $globalAdmin->notifications()->firstOrFail();
        $this->assertSame('Hospital admin email updated', $notification->data['title']);
        $this->assertSame(
            "Test Hospital changed its administrator email from {$previousEmail} to updated-admin@example.test.",
            $notification->data['message'],
        );

        $this->actingAs($globalAdmin)
            ->get(route('global-admin.dashboard'))
            ->assertOk()
            ->assertSee('updated-admin@example.test');

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Platform admin')
            ->assertSee('Hospital admin email updated');

        $this->getJson(route('notifications.latest'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Hospital admin email updated');
    }

    public function test_staff_editing_a_hospital_admin_email_notifies_global_admins(): void
    {
        $hospital = Hospital::create([
            'name' => 'Test Hospital',
            'slug' => 'test-hospital',
            'is_active' => true,
        ]);
        $hospitalAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $hospitalAdmin->hospital()->associate($hospital);
        $hospitalAdmin->save();
        $staffAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $staffAdmin->hospital()->associate($hospital);
        $staffAdmin->save();

        $globalAdmin = User::factory()->create(['role' => Role::Admin, 'is_active' => true]);
        $globalAdmin->is_global_admin = true;
        $globalAdmin->save();

        $this->actingAs($hospitalAdmin)
            ->put(route('staff.update', $staffAdmin), [
                'name' => $staffAdmin->name,
                'email' => 'updated-staff-admin@example.test',
                'role' => Role::Admin->value,
                'department_id' => null,
            ])
            ->assertRedirect(route('staff.index'));

        $this->assertDatabaseHas('users', [
            'id' => $staffAdmin->id,
            'email' => 'updated-staff-admin@example.test',
        ]);
        $this->assertSame(
            'Hospital admin email updated',
            $globalAdmin->notifications()->firstOrFail()->data['title'],
        );
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
