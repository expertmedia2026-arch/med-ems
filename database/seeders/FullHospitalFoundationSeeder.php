<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\Drug;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\Patient;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FullHospitalFoundationSeeder extends Seeder
{
    public int $hospitalId = 1;

    private const PASSWORD = 'password';

    private const DEPARTMENTS = [
        ['General Medicine', 'Outpatient assessment, general medical care, and inpatient services.', true],
        ['Paediatrics', 'Medical care for infants, children, and adolescents.', true],
        ['Obstetrics and Gynaecology', 'Pregnancy, childbirth, and women’s health services.', true],
        ['Dental', 'Oral health, dental assessment, and treatment.', false],
        ['Cardiology', 'Assessment and care for heart and circulatory conditions.', false],
        ['Dermatology', 'Assessment and treatment of skin conditions.', false],
        ['Ear, Nose and Throat', 'Assessment and care for ear, nose, and throat conditions.', false],
        ['Ophthalmology', 'Eye assessment and vision care.', false],
        ['Laboratory', 'Laboratory investigations and diagnostic services.', false],
        [Department::PHARMACY_NAME, 'Medication dispensing, stock control, and pharmacy services.', false],
        [Department::FINANCE_NAME, 'Payment collection, accounting, and financial oversight.', false],
        ['Accident & Emergency', 'Emergency assessment and urgent care.', true],
    ];

    private const STAFF = [
        ['Chukwudi Okafor', Role::Admin, null],
        ['Chiamaka Nwosu', Role::Receptionist, null],
        ['Chukwudi Nnamani', Role::Doctor, 'General Medicine'],
        ['Adaeze Nwankwo', Role::Doctor, 'General Medicine'],
        ['Uchenna Eze', Role::Nurse, 'General Medicine'],
        ['Ijeoma Obi', Role::Nurse, 'General Medicine'],
        ['Ngozi Okeke', Role::ChiefNurse, 'General Medicine'],
        ['Emeka Nwosu', Role::Doctor, 'Paediatrics'],
        ['Chisom Okeke', Role::Nurse, 'Paediatrics'],
        ['Nnamdi Umeh', Role::Doctor, 'Obstetrics and Gynaecology'],
        ['Adaobi Nnamani', Role::Nurse, 'Obstetrics and Gynaecology'],
        ['Obinna Madu', Role::Doctor, 'Dental'],
        ['Ozioma Nwankwo', Role::Nurse, 'Dental'],
        ['Ifeanyi Anyanwu', Role::Doctor, 'Cardiology'],
        ['Nneka Nwachukwu', Role::Nurse, 'Cardiology'],
        ['Kelechi Onoh', Role::Doctor, 'Dermatology'],
        ['Amarachi Ojukwu', Role::Nurse, 'Dermatology'],
        ['Chinedu Ekwueme', Role::Doctor, 'Ear, Nose and Throat'],
        ['Ugochi Nnamani', Role::Nurse, 'Ear, Nose and Throat'],
        ['Okechukwu Nnaji', Role::Doctor, 'Ophthalmology'],
        ['Oluchi Okorie', Role::Nurse, 'Ophthalmology'],
        ['Ifeoma Eze', Role::Lab, 'Laboratory'],
        ['Somtochukwu Okafor', Role::Lab, 'Laboratory'],
        ['Chika Okafor', Role::ChiefPharmacist, Department::PHARMACY_NAME],
        ['Ngozi Madu', Role::Pharmacist, Department::PHARMACY_NAME],
        ['Ezeani Okoro', Role::ChiefAccountant, Department::FINANCE_NAME],
        ['Adaora Eze', Role::Accountant, Department::FINANCE_NAME],
        ['Chibuzo Onyekachi', Role::Doctor, 'Accident & Emergency'],
        ['Nkiru Udeh', Role::Nurse, 'Accident & Emergency'],
        ['Chinonso Eze', Role::MortuaryAttendant, null],
    ];

    private const PATIENTS = [
        ['Adaeze', 'Okafor', 'female'], ['Chinedu', 'Nwankwo', 'male'],
        ['Chiamaka', 'Eze', 'female'], ['Emeka', 'Obi', 'male'],
        ['Ngozi', 'Okeke', 'female'], ['Obinna', 'Nwosu', 'male'],
        ['Ifeoma', 'Umeh', 'female'], ['Kelechi', 'Madu', 'male'],
        ['Nneka', 'Anyanwu', 'female'], ['Ifeanyi', 'Nwachukwu', 'male'],
        ['Ugochi', 'Onoh', 'female'], ['Chukwudi', 'Ojukwu', 'male'],
        ['Amarachi', 'Ekwueme', 'female'], ['Nnamdi', 'Nnamani', 'male'],
        ['Ogechi', 'Nnaji', 'female'], ['Okechukwu', 'Okorie', 'male'],
        ['Adaobi', 'Onyema', 'female'], ['Ikenna', 'Udeh', 'male'],
        ['Ijeoma', 'Onwudiwe', 'female'], ['Somtochukwu', 'Onyekachi', 'male'],
        ['Oluchi', 'Ezenwa', 'female'], ['Chibuzo', 'Ugwu', 'male'],
        ['Nkiru', 'Ibe', 'female'], ['Chinonso', 'Ndukwe', 'male'],
        ['Ezinne', 'Nwafor', 'female'], ['Olisa', 'Nwoke', 'male'],
        ['Ozioma', 'Ezeani', 'female'], ['Nonso', 'Onuoha', 'male'],
        ['Adanna', 'Nwankpa', 'female'], ['Uchenna', 'Ezeoke', 'male'],
        ['Chinyere', 'Okonkwo', 'female'], ['Chisom', 'Opara', 'male'],
        ['Adaora', 'Nwogu', 'female'], ['Chukwuka', 'Onyeka', 'male'],
        ['Nnenna', 'Ekwunife', 'female'], ['Chijioke', 'Mbah', 'male'],
        ['Uju', 'Nnajiofor', 'female'], ['Chidiebere', 'Ofor', 'male'],
        ['Nkemdilim', 'Uzor', 'female'], ['Chigozie', 'Ezeudu', 'male'],
    ];

    public function run(): void
    {
        $hospital = Hospital::query()->find($this->hospitalId);
        if (! $hospital) {
            throw new RuntimeException("Hospital ID {$this->hospitalId} does not exist.");
        }

        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set($this->hospitalId);

        try {
            DB::transaction(function () use ($hospital): void {
                $this->seedHospitalConfiguration($hospital);
                $departments = $this->seedDepartments();
                $this->seedStaff($hospital, $departments);
                $this->seedPatients($hospital);
            }, 3);

            $this->call(ConsultationPricingSeeder::class);
            $this->seedClinicalCatalog();
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info("Seeded departments, Igbo staff, consultation prices, catalogs, and 40 patients for hospital ID {$this->hospitalId}.");
    }

    private function seedHospitalConfiguration(Hospital $hospital): void
    {
        HospitalSetting::updateOrCreate(
            ['hospital_id' => $hospital->id],
            [
                'name' => $hospital->name,
                'address' => 'Enugu, Enugu State, Nigeria',
                'registration_fee' => 2000,
                'emergency_fee' => 7500,
                'oxygen_per_administration_fee' => 1500,
                'nebulizer_fee_per_three_uses' => 2500,
                'sugar_check_fee' => 1000,
            ],
        );

        foreach ([
            'pharmacy', 'mortuary', 'emergency', 'inpatient', 'laboratory', 'external_lab',
            'staff_roster', 'appointments', 'patient_id_card', 'patient_portal', 'central_vitals',
            'medication_administration', 'emergency_pharmacy', 'maternity', 'lab_inventory',
            'online_payments', 'finance_reports',
        ] as $module) {
            HospitalModule::updateOrCreate(['module' => $module], ['enabled' => true]);
        }

        HospitalWorkflow::updateOrCreate(
            ['workflow' => 'admission', 'version' => 1],
            [
                'definition' => [
                    'template' => 'complete',
                    'sections' => collect([
                        'admission_details', 'vitals', 'care_notes', 'sugar_checks',
                        'fluid_balance', 'oxygen', 'nebulizer', 'insulin_machine',
                    ])->map(fn (string $key): array => [
                        'key' => $key,
                        'enabled' => true,
                        'required' => in_array($key, ['admission_details', 'vitals'], true),
                    ])->all(),
                    'custom_fields' => [],
                ],
                'is_active' => true,
                'published_at' => now(),
            ],
        );
    }

    /** @return array<string, Department> */
    private function seedDepartments(): array
    {
        $departments = [];

        foreach (self::DEPARTMENTS as [$name, $description, $hasWard]) {
            $departments[$name] = Department::updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'has_ward' => $hasWard],
            );
        }

        return $departments;
    }

    /** @param array<string, Department> $departments */
    private function seedStaff(Hospital $hospital, array $departments): void
    {
        $emailDomain = Str::limit($hospital->slug, 40, '').'.demo.test';
        $departmentHeads = [];

        foreach (self::STAFF as $index => [$name, $role, $departmentName]) {
            $email = sprintf('%s.%02d@%s', str_replace('_', '.', $role->value), $index + 1, $emailDomain);
            $department = $departmentName ? $departments[$departmentName] : null;
            $staff = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'role' => $role,
                    'department_id' => $department?->id,
                    'password' => self::PASSWORD,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'is_global_admin' => false,
                ],
            );

            if ($department && ! isset($departmentHeads[$departmentName]) && in_array($role, [Role::Doctor, Role::ChiefNurse, Role::ChiefPharmacist, Role::ChiefAccountant], true)) {
                $departmentHeads[$departmentName] = $staff->id;
            }

            if ($role === Role::Doctor) {
                foreach ([2, 9, 16] as $daysAhead) {
                    DoctorAvailability::updateOrCreate([
                        'doctor_id' => $staff->id,
                        'date' => now()->addDays($daysAhead)->startOfDay(),
                    ]);
                }
            }
        }

        foreach ($departmentHeads as $departmentName => $headId) {
            $departments[$departmentName]->update(['hod_id' => $headId]);
        }
    }

    private function seedPatients(Hospital $hospital): void
    {
        $locations = ['New Haven', 'Independence Layout', 'GRA', 'Trans-Ekulu', 'Achara Layout', 'Abakpa Nike'];
        $bloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-'];
        $genotypes = ['AA', 'AA', 'AS', 'AA', 'AC'];
        $emailDomain = Str::limit($hospital->slug, 40, '').'.demo.patient.test';

        foreach (self::PATIENTS as $index => [$firstName, $lastName, $gender]) {
            $number = $index + 1;
            $isNhis = $number <= 24;
            $birthDate = sprintf(
                '%04d-%02d-%02d',
                1945 + (($number * 7) % 70),
                (($number - 1) % 12) + 1,
                (($number * 3 - 1) % 28) + 1,
            );

            Patient::updateOrCreate(
                ['email' => sprintf('patient%02d@%s', $number, $emailDomain)],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => $gender,
                    'date_of_birth' => $birthDate,
                    'phone' => sprintf('080%02d%06d', $this->hospitalId % 100, $number),
                    'address' => $locations[$index % count($locations)].', Enugu',
                    'blood_group' => $bloodGroups[$index % count($bloodGroups)],
                    'genotype' => $genotypes[$index % count($genotypes)],
                    'allergies' => $number % 11 === 0 ? 'Sulfonamides' : null,
                    'next_of_kin_name' => 'Chinwe '.$lastName,
                    'next_of_kin_phone' => sprintf('081%02d%06d', $this->hospitalId % 100, $number),
                    'is_nhis' => $isNhis,
                    'nhis_number' => $isNhis ? sprintf('NHIS-H%d-%06d', $this->hospitalId, $number) : null,
                ],
            );
        }
    }

    private function seedClinicalCatalog(): void
    {
        $existingStock = Drug::query()->pluck('stock_quantity', 'id');

        $this->call(ClinicalSeeder::class);

        foreach ($existingStock as $drugId => $stockQuantity) {
            Drug::query()->whereKey($drugId)->update(['stock_quantity' => $stockQuantity]);
        }
    }
}
