<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DepartmentStaffSeeder extends Seeder
{
    public const DEPARTMENTS = [
        ['General Medicine', 'Outpatient assessment and general medical care.'],
        ['Paediatrics', 'Medical care for infants, children, and adolescents.'],
        ['Obstetrics and Gynaecology', 'Pregnancy, childbirth, and women’s health services.'],
        ['Dental', 'Oral health, dental assessment, and treatment.'],
        ['Cardiology', 'Assessment and care for heart and circulatory conditions.'],
        ['Dermatology', 'Assessment and treatment of skin conditions.'],
        ['Ear, Nose and Throat', 'Assessment and care for ear, nose, and throat conditions.'],
        ['Ophthalmology', 'Eye assessment and vision care.'],
    ];

    private const DOCTORS = [
        [['Chukwudi', 'Okafor'], ['Adaeze', 'Nwankwo']],
        [['Emeka', 'Eze'], ['Ngozi', 'Obi']],
        [['Nnamdi', 'Okeke'], ['Ifeoma', 'Nwosu']],
        [['Obinna', 'Madu'], ['Chiamaka', 'Umeh']],
        [['Ifeanyi', 'Anyanwu'], ['Nneka', 'Nwachukwu']],
        [['Kelechi', 'Onoh'], ['Amarachi', 'Ojukwu']],
        [['Chinedu', 'Ekwueme'], ['Ugochi', 'Nnamani']],
        [['Okechukwu', 'Nnaji'], ['Oluchi', 'Okorie']],
    ];

    private const NURSES = [
        [['Uchenna', 'Okafor'], ['Ozioma', 'Nwankwo']],
        [['Chisom', 'Eze'], ['Adanna', 'Obi']],
        [['Somtochukwu', 'Okeke'], ['Ijeoma', 'Nwosu']],
        [['Chinonso', 'Madu'], ['Ezinne', 'Umeh']],
        [['Nonso', 'Anyanwu'], ['Adaora', 'Nwachukwu']],
        [['Ikenna', 'Onoh'], ['Ogechi', 'Ojukwu']],
        [['Chibuzo', 'Ekwueme'], ['Nkiru', 'Nnamani']],
        [['Olisa', 'Nnaji'], ['Chinyere', 'Okorie']],
    ];

    public function run(): void
    {
        $financeDepartment = Department::updateOrCreate(
            ['name' => Department::FINANCE_NAME],
            ['description' => 'Payment collection, accounting, and financial oversight.'],
        );
        $pharmacyDepartment = Department::updateOrCreate(
            ['name' => Department::PHARMACY_NAME],
            ['description' => 'Medication dispensing, inventory, and pharmacy oversight.'],
        );
        $emergencyDepartment = Department::updateOrCreate(
            ['name' => 'Accident & Emergency'],
            ['description' => 'Emergency assessment and urgent care.'],
        );
        Department::where('name', 'General Medicine')->update(['has_ward' => true]);

        foreach (self::DEPARTMENTS as $departmentIndex => [$name, $description]) {
            $department = Department::updateOrCreate(['name' => $name], ['description' => $description]);

            foreach ([Role::Doctor, Role::Nurse] as $role) {
                $nameGroups = $role === Role::Doctor ? self::DOCTORS : self::NURSES;
                $prefix = $role === Role::Doctor ? 'Dr.' : 'Nurse';
                $slug = $role->value;

                foreach ($nameGroups[$departmentIndex] as $slot => [$firstName, $lastName]) {
                    $email = match ([$departmentIndex, $slot, $role]) {
                        [0, 0, Role::Doctor] => 'doctor@hms.test',
                        [0, 0, Role::Nurse] => 'nurse@hms.test',
                        default => sprintf('%s.%s.%s@hms.test', $slug, Str::slug($firstName), Str::slug($lastName)),
                    };

                    $staff = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => $prefix.' '.$firstName.' '.$lastName,
                            'role' => $role,
                            'department_id' => $department->id,
                            'password' => 'password',
                            'email_verified_at' => now(),
                            'is_active' => true,
                        ],
                    );

                    if ($role === Role::Doctor) {
                        $daysInMonth = now()->daysInMonth;
                        $dates = collect(range(1, $daysInMonth))->shuffle()->take(3);

                        foreach ($dates as $day) {
                            $date = Carbon::create(now()->year, now()->month, $day)->toDateString();
                            DoctorAvailability::updateOrCreate(
                                ['doctor_id' => $staff->id, 'date' => $date],
                                [],
                            );
                        }
                    }
                }
            }
        }

        $supportStaff = [
            ['Chukwudi Okafor', 'admin@hms.test', Role::Admin],
            ['Chiamaka Nwosu', 'reception@hms.test', Role::Receptionist],
            ['Ifeanyi Eze', 'lab@hms.test', Role::Lab],
            ['Ngozi Madu', 'pharmacy@hms.test', Role::Pharmacist],
            ['Chika Okafor', 'chief.pharmacist@hms.test', Role::ChiefPharmacist],
            ['Adaobi Eze', 'accountant@hms.test', Role::Accountant],
            ['Ezeani Okoro', 'chief.accountant@hms.test', Role::ChiefAccountant],
        ];

        foreach ($supportStaff as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'role' => $role,
                    'department_id' => $role->isFinanceStaff()
                        ? $financeDepartment->id
                        : ($role->isPharmacyStaff() ? $pharmacyDepartment->id : null),
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['Dr. A&E Demo', 'er.doctor@hms.test', Role::Doctor],
            ['Nurse A&E Demo', 'er.nurse@hms.test', Role::Nurse],
        ] as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'role' => $role,
                    'department_id' => $emergencyDepartment->id,
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
        }

        User::updateOrCreate(
            ['email' => 'mortuary@hms.test'],
            [
                'name' => 'Mortuary Attendant Demo',
                'role' => Role::MortuaryAttendant,
                'department_id' => null,
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
    }
}
