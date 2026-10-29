<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteGlobalAdmin extends Command
{
    protected $signature = 'saas:promote-global-admin {email : Existing user email to promote}';

    protected $description = 'Grant global hospital onboarding access to an existing user';

    public function handle(): int
    {
        $user = User::withoutGlobalScopes()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email. Create the user first, then run this command.');

            return self::FAILURE;
        }

        $user->forceFill([
            'is_global_admin' => true,
            'is_platform_super_admin' => true,
            'hospital_id' => null,
            'department_id' => null,
        ])->save();
        $this->info("{$user->email} is now a platform-only global admin and can access /global-admin/hospitals.");

        return self::SUCCESS;
    }
}
