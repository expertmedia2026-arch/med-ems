<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\Drug;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UrologyCentreFoundationSeeder extends Seeder
{
    public int $hospitalId = 1;

    private const PASSWORD = 'password';

    private const DEPARTMENTS = [
        ['Urology Outpatient Clinic', 'Specialist urology consultations, reviews, and outpatient care.', false],
        ['Urology Ward', 'Inpatient urology observation, treatment, and perioperative care.', true],
        ['Theatre & Anaesthesia', 'Operating theatre, anaesthesia, recovery, and perioperative services.', false],
        ['Diagnostic Imaging', 'Ultrasound, radiography, and specialist diagnostic imaging services.', false],
        ['Laboratory', 'Urology-focused pathology and general diagnostic laboratory services.', false],
        [Department::PHARMACY_NAME, 'Medication dispensing, inventory, and clinical pharmacy services.', false],
        [Department::FINANCE_NAME, 'Billing, payment collection, insurance, and financial reporting.', false],
        ['Accident & Emergency', 'Emergency assessment and stabilization for acute urological presentations.', true],
    ];

    private const STAFF = [
        ['Chukwudi Okafor', Role::Admin, null],
        ['Chiamaka Nwosu', Role::Receptionist, null],
        ['Adaeze Nwankwo', Role::Receptionist, null],
        ['Dr. Chinedu Nnamani', Role::Doctor, 'Urology Outpatient Clinic'],
        ['Dr. Emeka Okeke', Role::Doctor, 'Urology Outpatient Clinic'],
        ['Dr. Ifeanyi Eze', Role::Doctor, 'Urology Outpatient Clinic'],
        ['Nurse Ijeoma Obi', Role::Nurse, 'Urology Outpatient Clinic'],
        ['Nurse Ugochi Nwafor', Role::Nurse, 'Urology Outpatient Clinic'],
        ['Dr. Obinna Umeh', Role::Doctor, 'Urology Ward'],
        ['Nurse Ngozi Madu', Role::ChiefNurse, 'Urology Ward'],
        ['Nurse Nneka Anyanwu', Role::Nurse, 'Urology Ward'],
        ['Nurse Amarachi Onoh', Role::Nurse, 'Urology Ward'],
        ['Dr. Nnamdi Ojukwu', Role::Doctor, 'Theatre & Anaesthesia'],
        ['Nurse Ozioma Ekwueme', Role::Nurse, 'Theatre & Anaesthesia'],
        ['Nurse Adaobi Nnaji', Role::Nurse, 'Theatre & Anaesthesia'],
        ['Dr. Kelechi Okorie', Role::Doctor, 'Diagnostic Imaging'],
        ['Nurse Oluchi Udeh', Role::Nurse, 'Diagnostic Imaging'],
        ['Ifeoma Onyekachi', Role::Lab, 'Laboratory'],
        ['Somtochukwu Ezenwa', Role::Lab, 'Laboratory'],
        ['Chika Nwachukwu', Role::ChiefPharmacist, Department::PHARMACY_NAME],
        ['Nkiru Onuoha', Role::Pharmacist, Department::PHARMACY_NAME],
        ['Ezeani Okoro', Role::ChiefAccountant, Department::FINANCE_NAME],
        ['Adaora Nwogu', Role::Accountant, Department::FINANCE_NAME],
        ['Dr. Chibuzo Ezeoke', Role::Doctor, 'Accident & Emergency'],
        ['Nurse Chisom Opara', Role::Nurse, 'Accident & Emergency'],
    ];

    private const PATIENTS = [
        ['Chinedu', 'Okafor', 'male', '1954-03-14'], ['Emeka', 'Nwankwo', 'male', '1961-07-22'],
        ['Obinna', 'Eze', 'male', '1948-11-05'], ['Ifeanyi', 'Obi', 'male', '1968-01-19'],
        ['Kelechi', 'Okeke', 'male', '1972-05-30'], ['Nnamdi', 'Nwosu', 'male', '1958-09-12'],
        ['Okechukwu', 'Umeh', 'male', '1946-02-27'], ['Chukwudi', 'Madu', 'male', '1980-06-08'],
        ['Ikenna', 'Anyanwu', 'male', '1986-10-16'], ['Somtochukwu', 'Nwachukwu', 'male', '1991-12-03'],
        ['Olisa', 'Onoh', 'male', '1976-04-25'], ['Chibuzo', 'Ojukwu', 'male', '1965-08-09'],
        ['Chinonso', 'Ekwueme', 'male', '1983-03-17'], ['Nonso', 'Nnamani', 'male', '1994-07-11'],
        ['Chijioke', 'Nnaji', 'male', '1951-01-29'], ['Chidiebere', 'Okorie', 'male', '1970-05-06'],
        ['Chigozie', 'Onyema', 'male', '1988-09-21'], ['Uchenna', 'Udeh', 'male', '1963-11-13'],
        ['Adaeze', 'Onwudiwe', 'female', '1977-02-18'], ['Chiamaka', 'Onyekachi', 'female', '1989-06-24'],
        ['Ngozi', 'Ezenwa', 'female', '1969-10-07'], ['Ifeoma', 'Ugwu', 'female', '1992-12-15'],
        ['Nneka', 'Ibe', 'female', '1981-04-10'], ['Ugochi', 'Ndukwe', 'female', '1996-08-28'],
        ['Amarachi', 'Nwafor', 'female', '1985-03-02'], ['Ogechi', 'Nwoke', 'female', '1974-07-20'],
        ['Adaobi', 'Ezeani', 'female', '1990-11-26'], ['Ijeoma', 'Onuoha', 'female', '1967-01-08'],
        ['Oluchi', 'Nwankpa', 'female', '1984-05-23'], ['Nkiru', 'Ezeoke', 'female', '1998-09-04'],
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
                $this->seedConsultationTypes($departments['Urology Outpatient Clinic']);
                $this->seedLaboratoryServices($departments['Laboratory']);
                $this->seedMedicines();
                $this->seedPatients($hospital);
            }, 3);
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info("Seeded the specialist urology foundation for hospital ID {$this->hospitalId}.");
    }

    private function seedHospitalConfiguration(Hospital $hospital): void
    {
        HospitalSetting::updateOrCreate(
            ['hospital_id' => $hospital->id],
            [
                'name' => $hospital->name,
                'address' => 'Enugu, Enugu State, Nigeria',
                'registration_fee' => 3000,
                'emergency_fee' => 10000,
                'oxygen_per_administration_fee' => 2000,
                'nebulizer_fee_per_three_uses' => 3000,
                'sugar_check_fee' => 1200,
            ],
        );

        $enabledModules = [
            'pharmacy', 'emergency', 'inpatient', 'laboratory', 'external_lab', 'staff_roster',
            'appointments', 'patient_id_card', 'patient_portal', 'central_vitals',
            'medication_administration', 'emergency_pharmacy', 'lab_inventory',
            'online_payments', 'finance_reports',
        ];
        foreach ([
            'pharmacy', 'mortuary', 'emergency', 'inpatient', 'laboratory', 'external_lab',
            'staff_roster', 'appointments', 'patient_id_card', 'patient_portal', 'central_vitals',
            'medication_administration', 'emergency_pharmacy', 'maternity', 'lab_inventory',
            'online_payments', 'finance_reports',
        ] as $module) {
            HospitalModule::updateOrCreate(
                ['module' => $module],
                ['enabled' => in_array($module, $enabledModules, true)],
            );
        }

        HospitalWorkflow::updateOrCreate(
            ['workflow' => 'admission', 'version' => 1],
            [
                'definition' => [
                    'template' => 'specialist_urology',
                    'sections' => collect([
                        'admission_details', 'vitals', 'care_notes', 'sugar_checks',
                        'fluid_balance', 'oxygen', 'nebulizer', 'insulin_machine',
                    ])->map(fn (string $key): array => [
                        'key' => $key,
                        'enabled' => true,
                        'required' => in_array($key, ['admission_details', 'vitals', 'care_notes'], true),
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
        $emailDomain = Str::limit($hospital->slug, 40, '').'.urology.demo.test';
        $departmentHeads = [];

        foreach (self::STAFF as $index => [$name, $role, $departmentName]) {
            $department = $departmentName ? $departments[$departmentName] : null;
            $staff = User::updateOrCreate(
                ['email' => sprintf('%s.%02d@%s', str_replace('_', '.', $role->value), $index + 1, $emailDomain)],
                [
                    'name' => $name,
                    'role' => $role,
                    'department_id' => $department?->id,
                    'password' => self::PASSWORD,
                    'email_verified_at' => now(),
                    'is_active' => true,
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

    private function seedConsultationTypes(Department $urologyClinic): void
    {
        foreach ([
            ['Initial Urology Consultation', 'Comprehensive first specialist assessment.', 15000],
            ['Urology Follow-up Consultation', 'Review of an established urological condition.', 10000],
            ['Andrology and Male Fertility Consultation', 'Specialist male fertility and sexual health assessment.', 20000],
            ['Post-operative Urology Review', 'Specialist review following a urological procedure.', 10000],
            ['Catheter Clinic Review', 'Review of catheter care, trial without catheter, or catheter-related symptoms.', 8000],
        ] as [$name, $description, $price]) {
            ConsultationType::updateOrCreate(
                ['department_id' => $urologyClinic->id, 'name' => $name],
                ['description' => $description, 'price' => $price, 'is_active' => true],
            );
        }
    }

    private function seedLaboratoryServices(Department $laboratory): void
    {
        foreach ([
            ['Total Prostate-Specific Antigen (PSA)', 'URO001', 'Tumour Markers', 'Blood', 24, 12000],
            ['Free Prostate-Specific Antigen (Free PSA)', 'URO002', 'Tumour Markers', 'Blood', 24, 15000],
            ['Urinalysis', 'URO003', 'Clinical Chemistry', 'Urine', 4, 2500],
            ['Urine Microscopy, Culture and Sensitivity', 'URO004', 'Microbiology', 'Urine', 72, 10000],
            ['Kidney Function Test', 'URO005', 'Clinical Chemistry', 'Blood', 24, 10000],
            ['Electrolytes, Urea and Creatinine', 'URO006', 'Clinical Chemistry', 'Blood', 8, 7500],
            ['Semen Analysis', 'URO007', 'Andrology', 'Semen', 24, 10000],
            ['Full Blood Count', 'URO008', 'Haematology', 'Blood', 8, 5000],
            ['Blood Group', 'URO009', 'Haematology', 'Blood', 24, 2000],
            ['Genotype', 'URO010', 'Haematology', 'Blood', 48, 2500],
            ['HIV Screening', 'URO011', 'Serology', 'Blood', 24, 2500],
            ['Hepatitis B Surface Antigen', 'URO012', 'Serology', 'Blood', 24, 3500],
        ] as [$name, $code, $category, $sampleType, $turnaround, $price]) {
            LabTest::updateOrCreate(
                ['department_id' => $laboratory->id, 'name' => $name],
                [
                    'code' => $code,
                    'category' => $category,
                    'sample_type' => $sampleType,
                    'turnaround_hours' => $turnaround,
                    'description' => $name.' laboratory service.',
                    'price' => $price,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedMedicines(): void
    {
        foreach ([
            ['Tamsulosin', 'Tamsulosin', 'Capsule', '400mcg', 450, 160, 20],
            ['Finasteride', 'Finasteride', 'Tablet', '5mg', 500, 120, 15],
            ['Solifenacin', 'Solifenacin succinate', 'Tablet', '5mg', 650, 90, 15],
            ['Oxybutynin', 'Oxybutynin hydrochloride', 'Tablet', '5mg', 350, 110, 15],
            ['Nitrofurantoin', 'Nitrofurantoin', 'Capsule', '100mg', 300, 180, 20],
            ['Ciprofloxacin', 'Ciprofloxacin', 'Tablet', '500mg', 250, 180, 20],
            ['Ceftriaxone', 'Ceftriaxone', 'Injection', '1g', 2500, 80, 10],
            ['Diclofenac', 'Diclofenac sodium', 'Tablet', '50mg', 120, 160, 20],
            ['Paracetamol', 'Paracetamol', 'Tablet', '500mg', 50, 300, 30],
            ['Potassium Citrate', 'Potassium citrate', 'Tablet', '1080mg', 400, 120, 15],
            ['Sildenafil', 'Sildenafil citrate', 'Tablet', '50mg', 1000, 80, 10],
            ['Normal Saline', 'Sodium chloride', 'Infusion', '0.9% 1L', 1800, 70, 10],
        ] as [$name, $genericName, $form, $strength, $price, $openingStock, $reorderLevel]) {
            $drug = Drug::query()->firstOrNew(['name' => $name, 'strength' => $strength]);
            $isNew = ! $drug->exists;
            $drug->fill([
                'generic_name' => $genericName,
                'form' => $form,
                'unit_price' => $price,
                'reorder_level' => $reorderLevel,
                'is_active' => true,
            ]);
            if ($isNew) {
                $drug->stock_quantity = $openingStock;
            }
            $drug->save();
        }
    }

    private function seedPatients(Hospital $hospital): void
    {
        $locations = ['New Haven', 'Independence Layout', 'GRA', 'Trans-Ekulu', 'Achara Layout', 'Abakpa Nike'];
        $emailDomain = Str::limit($hospital->slug, 40, '').'.urology.demo.patient.test';

        foreach (self::PATIENTS as $index => [$firstName, $lastName, $gender, $dateOfBirth]) {
            $number = $index + 1;
            $isNhis = $number <= 18;

            Patient::updateOrCreate(
                ['email' => sprintf('patient%02d@%s', $number, $emailDomain)],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => $gender,
                    'date_of_birth' => $dateOfBirth,
                    'phone' => sprintf('070%02d%06d', $this->hospitalId % 100, $number),
                    'address' => $locations[$index % count($locations)].', Enugu',
                    'blood_group' => ['O+', 'A+', 'B+', 'AB+'][$index % 4],
                    'genotype' => ['AA', 'AA', 'AS', 'AA'][$index % 4],
                    'allergies' => $number % 13 === 0 ? 'Penicillin' : null,
                    'next_of_kin_name' => 'Chinwe '.$lastName,
                    'next_of_kin_phone' => sprintf('071%02d%06d', $this->hospitalId % 100, $number),
                    'is_nhis' => $isNhis,
                    'nhis_number' => $isNhis ? sprintf('NHIS-UC-H%d-%05d', $this->hospitalId, $number) : null,
                ],
            );
        }
    }
}
