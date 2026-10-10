<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentStaffSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['General Medicine', 'Outpatient assessment and general medical care.'],
            ['Paediatrics', 'Medical care for infants, children, and adolescents.'],
            ['Obstetrics and Gynaecology', 'Pregnancy, childbirth, and women’s health services.'],
            ['Surgery', 'Pre-operative, surgical, and post-operative care.'],
            ['Dental', 'Oral health, dental assessment, and treatment.'],
            ['Emergency', 'Urgent assessment and stabilization.'],
        ];

        $doctorNames = [
            ['Chukwudi', 'Okafor'], ['Emeka', 'Nwankwo'], ['Obinna', 'Eze'],
            ['Kelechi', 'Obi'], ['Nnamdi', 'Okeke'], ['Ifeanyi', 'Nwosu'],
            ['Chibuzo', 'Madu'], ['Okechukwu', 'Umeh'], ['Chinedu', 'Onyeka'],
            ['Somtochukwu', 'Ekwueme'], ['Uchenna', 'Anyanwu'], ['Chukwuemeka', 'Onoh'],
            ['Nonso', 'Nwachukwu'], ['Olisa', 'Ojukwu'], ['Ikenna', 'Ogbuefi'],
            ['Adaeze', 'Okorie'], ['Chiamaka', 'Nnamani'], ['Ngozi', 'Eze'],
            ['Ifeoma', 'Okafor'], ['Nneka', 'Nwankwo'], ['Ugochi', 'Obi'],
            ['Amarachi', 'Okeke'], ['Chika', 'Nwosu'], ['Oluchi', 'Madu'],
            ['Chinwe', 'Umeh'], ['Chinyere', 'Onyeka'], ['Nkiru', 'Ekwueme'],
            ['Adaora', 'Anyanwu'], ['Ebere', 'Onoh'], ['Ogechi', 'Nwachukwu'],
        ];

        $nurseNames = [
            ['Ifeanyi', 'Okafor'], ['Chisom', 'Nwankwo'], ['Ugochukwu', 'Eze'],
            ['Ozioma', 'Obi'], ['Chinonso', 'Okeke'], ['Amaka', 'Nwosu'],
            ['Uche', 'Madu'], ['Ijeoma', 'Umeh'], ['Chiamaka', 'Onyeka'],
            ['Nkem', 'Ekwueme'], ['Chika', 'Anyanwu'], ['Adanna', 'Onoh'],
            ['Ihuoma', 'Nwachukwu'], ['Somto', 'Ojukwu'], ['Kamsiyochukwu', 'Ogbuefi'],
            ['Nneka', 'Okorie'], ['Ngozi', 'Nnamani'], ['Adaeze', 'Eze'],
            ['Ifeoma', 'Okafor'], ['Ugochi', 'Nwankwo'], ['Chinwe', 'Obi'],
            ['Amarachi', 'Okeke'], ['Oluchi', 'Nwosu'], ['Ezinne', 'Madu'],
            ['Nkiru', 'Umeh'], ['Chinyere', 'Onyeka'], ['Ogechi', 'Ekwueme'],
            ['Chisara', 'Anyanwu'], ['Adaora', 'Onoh'], ['Ujunwa', 'Nwachukwu'],
        ];

        $staffGroups = [
            ['role' => Role::Doctor, 'prefix' => 'Dr.', 'slug' => 'doctor', 'names' => $doctorNames],
            ['role' => Role::Nurse, 'prefix' => 'Nurse', 'slug' => 'nurse', 'names' => $nurseNames],
        ];

        foreach ($departments as $departmentIndex => [$name, $description]) {
            $department = Department::updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );

            foreach ($staffGroups as $group) {
                for ($slot = 0; $slot < 5; $slot++) {
                    $nameIndex = $departmentIndex * 5 + $slot;
                    [$firstName, $lastName] = $group['names'][$nameIndex];
                    $departmentSlug = Str::slug($name);
                    $email = sprintf(
                        '%s.%s.%s.%s@hms.test',
                        $group['slug'],
                        Str::slug($firstName),
                        Str::slug($lastName),
                        $departmentSlug,
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

        // Keep the existing demo login accounts attached to clinical departments too.
        User::where('email', 'doctor@hms.test')->update([
            'department_id' => Department::where('name', 'General Medicine')->value('id'),
        ]);
        User::where('email', 'nurse@hms.test')->update([
            'department_id' => Department::where('name', 'Paediatrics')->value('id'),
        ]);
    }
}
