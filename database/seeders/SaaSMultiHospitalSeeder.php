<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaaSMultiHospitalSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const MODULES = [
        'pharmacy', 'mortuary', 'emergency', 'inpatient', 'laboratory', 'external_lab',
        'staff_roster', 'appointments', 'patient_id_card', 'patient_portal', 'central_vitals',
        'medication_administration', 'emergency_pharmacy', 'lab_inventory',
        'online_payments', 'finance_reports',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SaaSMultiHospitalSeeder is for local or testing environments only.');
        }

        $context = app(HospitalContext::class);
        $context->set(null);

        try {
            DB::transaction(function () use ($context): void {
                foreach ($this->hospitals() as $hospitalData) {
                    $hospital = Hospital::updateOrCreate(
                        ['slug' => $hospitalData['slug']],
                        ['name' => $hospitalData['name'], 'is_active' => true],
                    );

                    $context->set((int) $hospital->id);
                    HospitalSetting::updateOrCreate(
                        ['hospital_id' => $hospital->id],
                        [
                            'name' => $hospital->name,
                            'address' => $hospitalData['address'],
                            'registration_fee' => 2000,
                        ],
                    );

                    foreach ($hospitalData['modules'] as $module => $enabled) {
                        HospitalModule::updateOrCreate(['module' => $module], ['enabled' => $enabled]);
                    }

                    $departments = $this->seedDepartments($hospitalData['modules']);
                    $this->seedStaff($hospital, $departments, $hospitalData['modules']);
                    $this->seedConsultationTypes($departments);
                    $this->seedPatients($hospitalData['slug']);

                    $this->command?->line(sprintf(
                        '%s admin: %s / %s',
                        $hospital->name,
                        $this->adminEmail($hospitalData['slug']),
                        self::PASSWORD,
                    ));
                }
            });
        } finally {
            $context->set(null);
        }

        $this->command?->info('Seeded 5 hospitals, 20 patients, hospital admins, and demo staff.');
    }

    private function hospitals(): array
    {
        return [
            [
                'name' => 'Complete Care Hospital', 'slug' => 'complete-care', 'address' => 'Central District, Lagos',
                'modules' => $this->enabledModules(self::MODULES),
            ],
            [
                'name' => 'Community Family Clinic', 'slug' => 'community-family', 'address' => 'Ikeja, Lagos',
                'modules' => $this->enabledModules([
                    'appointments', 'patient_id_card', 'patient_portal', 'staff_roster', 'online_payments',
                ]),
            ],
            [
                'name' => 'Lakeside General Hospital', 'slug' => 'lakeside-general', 'address' => 'Nsukka, Enugu',
                'modules' => $this->enabledModules([
                    'pharmacy', 'inpatient', 'laboratory', 'external_lab', 'staff_roster', 'appointments',
                    'patient_id_card', 'patient_portal', 'central_vitals', 'medication_administration',
                    'lab_inventory', 'online_payments', 'finance_reports',
                ]),
            ],
            [
                'name' => 'Emergency Trauma Centre', 'slug' => 'emergency-trauma', 'address' => 'Port Harcourt, Rivers',
                'modules' => $this->enabledModules([
                    'pharmacy', 'emergency', 'inpatient', 'laboratory', 'external_lab', 'staff_roster',
                    'appointments', 'patient_id_card', 'patient_portal', 'central_vitals', 'medication_administration',
                    'emergency_pharmacy', 'lab_inventory', 'online_payments', 'finance_reports',
                ]),
            ],
            [
                'name' => 'St Mary’s Maternity Hospital', 'slug' => 'st-marys-maternity', 'address' => 'Ibadan, Oyo',
                'modules' => $this->enabledModules([
                    'pharmacy', 'inpatient', 'laboratory', 'staff_roster', 'appointments', 'patient_id_card',
                    'patient_portal', 'medication_administration', 'online_payments', 'finance_reports',
                ]),
            ],
        ];
    }

    private function enabledModules(array $enabled): array
    {
        $modules = array_fill_keys(self::MODULES, false);
        foreach ($enabled as $module) {
            $modules[$module] = true;
        }

        return $modules;
    }

    private function seedDepartments(array $modules): array
    {
        $definitions = [
            'General Medicine' => ['description' => 'Outpatient consultations and general care.', 'has_ward' => (bool) $modules['inpatient']],
            'Paediatrics' => ['description' => 'Child health and paediatric care.', 'has_ward' => false],
            'Nursing' => ['description' => 'Nursing and patient care.', 'has_ward' => false],
            Department::FINANCE_NAME => ['description' => 'Billing and hospital finance.', 'has_ward' => false],
        ];

        if ($modules['pharmacy']) {
            $definitions[Department::PHARMACY_NAME] = ['description' => 'Medication dispensing and stock management.', 'has_ward' => false];
        }
        if ($modules['laboratory']) {
            $definitions['Laboratory'] = ['description' => 'Diagnostic laboratory services.', 'has_ward' => false];
        }
        if ($modules['emergency']) {
            $definitions['Accident & Emergency'] = ['description' => 'Emergency assessment and treatment.', 'has_ward' => false];
        }
        if ($modules['mortuary']) {
            $definitions['Mortuary'] = ['description' => 'Mortuary services.', 'has_ward' => false];
        }

        $departments = [];
        foreach ($definitions as $name => $attributes) {
            $departments[$name] = Department::updateOrCreate(['name' => $name], $attributes);
        }

        return $departments;
    }

    private function seedStaff(Hospital $hospital, array $departments, array $modules): void
    {
        $slug = $hospital->slug;
        $this->user($this->adminEmail($slug), $hospital->name.' Admin', Role::Admin, $departments['General Medicine']->id);
        $this->user("reception@{$slug}.test", 'Demo Receptionist', Role::Receptionist, $departments['General Medicine']->id);

        foreach ([1, 2] as $number) {
            $this->user("doctor{$number}@{$slug}.test", "Dr Demo {$number}", Role::Doctor, $departments['General Medicine']->id);
            $this->user("nurse{$number}@{$slug}.test", "Demo Nurse {$number}", Role::Nurse, $departments['Nursing']->id);
        }

        $this->user("accountant@{$slug}.test", 'Demo Accountant', Role::Accountant, $departments[Department::FINANCE_NAME]->id);
        if ($modules['pharmacy']) {
            $this->user("pharmacist@{$slug}.test", 'Demo Pharmacist', Role::Pharmacist, $departments[Department::PHARMACY_NAME]->id);
        }
        if ($modules['laboratory']) {
            $this->user("lab@{$slug}.test", 'Demo Lab Scientist', Role::Lab, $departments['Laboratory']->id);
        }
        if ($modules['mortuary']) {
            $this->user("mortuary@{$slug}.test", 'Demo Mortuary Attendant', Role::MortuaryAttendant, $departments['Mortuary']->id);
        }
    }

    private function seedConsultationTypes(array $departments): void
    {
        foreach ([
            ['General Medicine', 'General Consultation', 'Initial medical assessment.', 5000],
            ['General Medicine', 'Follow-up Consultation', 'Review visit for an existing concern.', 3000],
            ['Paediatrics', 'Paediatric Consultation', 'Medical assessment for a child.', 5000],
        ] as [$departmentName, $name, $description, $price]) {
            ConsultationType::updateOrCreate(
                ['department_id' => $departments[$departmentName]->id, 'name' => $name],
                ['description' => $description, 'price' => $price, 'is_active' => true],
            );
        }
    }

    private function seedPatients(string $slug): void
    {
        $firstNames = ['Amara', 'Chidi', 'Zainab', 'Tunde'];
        $lastNames = ['Okafor', 'Bello', 'Adeyemi', 'Eze'];
        $phonePrefix = str_pad((string) (abs(crc32($slug)) % 10000), 4, '0', STR_PAD_LEFT);

        foreach ($firstNames as $index => $firstName) {
            $number = $index + 1;
            Patient::updateOrCreate(
                ['hospital_number' => strtoupper(str_replace('-', '', $slug)).'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT)],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastNames[$index],
                    'gender' => $index % 2 === 0 ? 'female' : 'male',
                    'date_of_birth' => now()->subYears(20 + $index * 7)->subDays($index * 19)->toDateString(),
                    'phone' => sprintf('080%s%04d', $phonePrefix, $number),
                    'email' => "patient{$number}@{$slug}.test",
                    'address' => 'Demo address, Nigeria',
                    'blood_group' => ['O+', 'A+', 'B+', 'AB+'][$index],
                    'genotype' => ['AA', 'AS', 'AA', 'AC'][$index],
                    'registration_paid_at' => now(),
                ],
            );
        }
    }

    private function user(string $email, string $name, Role $role, int $departmentId): User
    {
        $hospitalId = app(HospitalContext::class)->id();
        $user = User::withoutGlobalScope('hospital')->where('email', $email)->first();
        if ($user && (int) $user->hospital_id !== $hospitalId) {
            throw new RuntimeException("{$email} is already assigned to another hospital.");
        }

        $user ??= new User;
        $user->name = $name;
        $user->email = $email;
        $user->role = $role;
        $user->hospital_id = $hospitalId;
        $user->department_id = $departmentId;
        $user->password = self::PASSWORD;
        $user->is_global_admin = false;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function adminEmail(string $slug): string
    {
        return "admin@{$slug}.test";
    }
}
