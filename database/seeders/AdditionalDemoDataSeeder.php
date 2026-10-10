<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdditionalDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::orderBy('id')->get();

        if ($departments->isEmpty()) {
            $this->call(DepartmentStaffSeeder::class);
            $departments = Department::orderBy('id')->get();
        }

        $doctorNames = [
            ['Chukwuma', 'Ofor'], ['Ejike', 'Ezeani'], ['Arinze', 'Mgbemena'],
            ['Okezie', 'Nwankwo'], ['Chukwunonso', 'Okorie'], ['Ugochukwu', 'Mbah'],
            ['Akachukwu', 'Nwafor'], ['Ifeanyichukwu', 'Onyema'], ['Chukwudi', 'Ibe'],
            ['Nnaemeka', 'Okoro'], ['Obioma', 'Ekwueme'], ['Ikenna', 'Nnaji'],
            ['Chibueze', 'Ude'], ['Ozioma', 'Nwankpa'], ['Somadina', 'Okwuosa'],
            ['Oluebube', 'Okoli'], ['Chinasa', 'Nwosu'], ['Adaugo', 'Eze'],
            ['Nwakaego', 'Okafor'], ['Obiageli', 'Madu'], ['Chizoba', 'Anyanwu'],
            ['Uju', 'Nwachukwu'], ['Adaku', 'Onoh'], ['Ihunanya', 'Obi'],
            ['Kosisochukwu', 'Ezeani'], ['Chinelo', 'Ojukwu'], ['Ndidiamaka', 'Umeh'],
            ['Ogechukwu', 'Mgbemena'], ['Chizaram', 'Nnaji'], ['Oluebubechukwu', 'Okoro'],
        ];

        $nurseNames = [
            ['Chukwuma', 'Okoro'], ['Ejike', 'Okafor'], ['Arinze', 'Eze'],
            ['Okezie', 'Nwosu'], ['Chukwunonso', 'Obi'], ['Ugochukwu', 'Nwachukwu'],
            ['Akachukwu', 'Okeke'], ['Ifeanyichukwu', 'Madu'], ['Chukwudi', 'Umeh'],
            ['Nnaemeka', 'Anyanwu'], ['Obioma', 'Nwankwo'], ['Ikenna', 'Onoh'],
            ['Chibueze', 'Ekwueme'], ['Ozioma', 'Nnaji'], ['Somadina', 'Onyeka'],
            ['Oluebube', 'Okafor'], ['Chinasa', 'Eze'], ['Adaugo', 'Nwosu'],
            ['Nwakaego', 'Obi'], ['Obiageli', 'Nwachukwu'], ['Chizoba', 'Okeke'],
            ['Uju', 'Madu'], ['Adaku', 'Umeh'], ['Ihunanya', 'Anyanwu'],
            ['Kosisochukwu', 'Nwankwo'], ['Chinelo', 'Onoh'], ['Ndidiamaka', 'Ekwueme'],
            ['Ogechukwu', 'Nnaji'], ['Chizaram', 'Onyeka'], ['Oluchi', 'Ojukwu'],
        ];

        $staffGroups = [
            ['role' => Role::Doctor, 'prefix' => 'Dr.', 'slug' => 'doctor', 'names' => $doctorNames],
            ['role' => Role::Nurse, 'prefix' => 'Nurse', 'slug' => 'nurse', 'names' => $nurseNames],
        ];

        foreach ($departments as $departmentIndex => $department) {
            foreach ($staffGroups as $group) {
                for ($slot = 0; $slot < 5; $slot++) {
                    $nameIndex = $departmentIndex * 5 + $slot;
                    [$firstName, $lastName] = $group['names'][$nameIndex];
                    $email = sprintf(
                        '%s.batch2.%s.%s.%s@hms.test',
                        $group['slug'],
                        Str::slug($firstName),
                        Str::slug($lastName),
                        Str::slug($department->name),
                    );

                    User::updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => $group['prefix'].' '.$firstName.' '.$lastName,
                            'role' => $group['role'],
                            'department_id' => $department->id,
                            'password' => 'password',
                            'email_verified_at' => now(),
                            'is_active' => true,
                        ],
                    );
                }
            }
        }

        $firstNames = [
            ['Chizoba', 'female'], ['Uju', 'female'], ['Oluebube', 'female'],
            ['Nwakaego', 'female'], ['Chinasa', 'female'], ['Obiageli', 'female'],
            ['Adaku', 'female'], ['Chukwuma', 'male'], ['Ejike', 'male'],
            ['Arinze', 'male'], ['Okezie', 'male'], ['Chukwunonso', 'male'],
            ['Akachukwu', 'male'], ['Somadina', 'male'],
        ];
        $surnames = [
            'Ofor', 'Ezeani', 'Mgbemena', 'Nwafor', 'Onyema',
            'Ibe', 'Okoro', 'Nnaji', 'Ude', 'Okwuosa',
        ];
        $bloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-'];
        $genotypes = ['AA', 'AS', 'AA', 'AC'];
        $index = 0;

        // Separate phone range and names make this a distinct, repeatable 140-patient batch.
        foreach ($firstNames as [$firstName, $gender]) {
            foreach ($surnames as $lastName) {
                $index++;
                $month = (($index - 1) % 12) + 1;
                $day = (($index - 1) % 28) + 1;
                $birthYear = 1968 + (($index - 1) % 42);
                $phone = '080800'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);

                Patient::updateOrCreate(
                    ['phone' => $phone],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'gender' => $gender,
                        'date_of_birth' => sprintf('%04d-%02d-%02d', $birthYear, $month, $day),
                        'blood_group' => $bloodGroups[($index - 1) % count($bloodGroups)],
                        'genotype' => $genotypes[($index - 1) % count($genotypes)],
                    ],
                );
            }
        }
    }
}
