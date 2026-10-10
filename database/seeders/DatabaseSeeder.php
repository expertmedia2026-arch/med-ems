<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Admin User',        'admin@hms.test',        Role::Admin],
            ['Funke Receptionist','reception@hms.test',    Role::Receptionist],
            ['Nurse Chioma',      'nurse@hms.test',        Role::Nurse],
            ['Dr. Tunde Bello',   'doctor@hms.test',       Role::Doctor],
            ['Lab Scientist Ife', 'lab@hms.test',          Role::Lab],
            ['Pharm. Emeka',      'pharmacy@hms.test',     Role::Pharmacist],
            ['Cashier Bisi',      'cashier@hms.test',      Role::Cashier],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => 'password',
                    'role'              => $role,
                    'email_verified_at' => now(),
                ]
            );
        }
        $this->call(DepartmentStaffSeeder::class);
        $this->call(ConsultationPricingSeeder::class);
        $this->call(PatientSeeder::class);
        $this->call(AdditionalDemoDataSeeder::class);
        $this->call(DemoRegistrationPaymentSeeder::class);
        $this->call(ClinicalSeeder::class);
    }
}
