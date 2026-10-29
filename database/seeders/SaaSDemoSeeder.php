<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\Patient;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaaSDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SaaSDemoSeeder is for local or testing environments only.');
        }

        $context = app(HospitalContext::class);
        $legacyHospital = Hospital::query()->orderBy('id')->first();

        if (! $legacyHospital) {
            throw new RuntimeException('Run the hospital tenant migrations before this seeder.');
        }

        DB::transaction(function () use ($context): void {
            $globalAdminEmail = env('SAAS_DEMO_GLOBAL_ADMIN_EMAIL', 'global-admin@hms.test');
            $globalAdminPassword = env('SAAS_DEMO_GLOBAL_ADMIN_PASSWORD', 'password');

            // The platform account has no tenant membership. Tenant-bound demo
            // records are seeded only after switching to the demo hospital.
            $context->set(null);
            $globalAdmin = User::withoutGlobalScope('hospital')->where('email', $globalAdminEmail)->first();
            $globalAdmin ??= new User;
            $globalAdmin->name = 'SaaS Global Admin Demo';
            $globalAdmin->email = $globalAdminEmail;
            $globalAdmin->role = Role::Admin;
            $globalAdmin->hospital_id = null;
            $globalAdmin->department_id = null;
            $globalAdmin->password = $globalAdminPassword;
            $globalAdmin->is_active = true;
            $globalAdmin->is_global_admin = true;
            $globalAdmin->is_platform_super_admin = true;
            $globalAdmin->email_verified_at = now();
            $globalAdmin->save();

            $hospital = Hospital::updateOrCreate(
                ['slug' => 'basic-demo-hospital'],
                ['name' => 'Basic Demo Hospital', 'is_active' => true],
            );
            $context->set((int) $hospital->id);

            HospitalSetting::updateOrCreate(
                ['hospital_id' => $hospital->id],
                ['name' => $hospital->name, 'address' => 'Demo address, Enugu, Nigeria', 'registration_fee' => 2000],
            );

            foreach ([
                'pharmacy' => true,
                'mortuary' => false,
                'emergency' => false,
                'inpatient' => true,
                'laboratory' => true,
                'external_lab' => true,
                'staff_roster' => true,
                'appointments' => true,
                'patient_id_card' => true,
                'patient_portal' => true,
                'central_vitals' => true,
                'medication_administration' => true,
                'emergency_pharmacy' => false,
                'maternity' => false,
                'lab_inventory' => true,
                'online_payments' => true,
                'finance_reports' => true,
            ] as $module => $enabled) {
                HospitalModule::updateOrCreate(['module' => $module], ['enabled' => $enabled]);
            }

            $workflowSections = [
                ['key' => 'admission_details', 'enabled' => true, 'required' => true],
                ['key' => 'vitals', 'enabled' => true, 'required' => true],
                ['key' => 'care_notes', 'enabled' => true, 'required' => false],
                ['key' => 'sugar_checks', 'enabled' => false, 'required' => false],
                ['key' => 'fluid_balance', 'enabled' => false, 'required' => false],
                ['key' => 'oxygen', 'enabled' => false, 'required' => false],
                ['key' => 'nebulizer', 'enabled' => false, 'required' => false],
                ['key' => 'insulin_machine', 'enabled' => false, 'required' => false],
            ];
            HospitalWorkflow::updateOrCreate(
                ['workflow' => 'admission', 'version' => 1],
                [
                    'definition' => ['template' => 'basic', 'sections' => $workflowSections, 'custom_fields' => []],
                    'is_active' => true,
                    'published_at' => now(),
                ],
            );
            HospitalWorkflow::query()
                ->where('workflow', 'admission')
                ->where('version', '!=', 1)
                ->update(['is_active' => false]);

            $medicine = Department::updateOrCreate(
                ['name' => 'General Medicine'],
                ['description' => 'Outpatient care and the basic inpatient ward.', 'has_ward' => true],
            );
            $paediatrics = Department::updateOrCreate(
                ['name' => 'Paediatrics'],
                ['description' => 'Basic child health and paediatric care.', 'has_ward' => false],
            );
            Department::updateOrCreate(
                ['name' => Department::PHARMACY_NAME],
                ['description' => 'Medication dispensing and stock management.', 'has_ward' => false],
            );
            Department::updateOrCreate(
                ['name' => 'Laboratory'],
                ['description' => 'Basic diagnostic laboratory services.', 'has_ward' => false],
            );

            foreach ([
                [$medicine, 'General Consultation', 'Initial general medical assessment.', 5000],
                [$medicine, 'Follow-up Consultation', 'Review visit for an existing concern.', 3000],
                [$paediatrics, 'Paediatric Consultation', 'Medical assessment for a child.', 5000],
            ] as [$department, $name, $description, $price]) {
                ConsultationType::updateOrCreate(
                    ['department_id' => $department->id, 'name' => $name],
                    ['description' => $description, 'price' => $price, 'is_active' => true],
                );
            }

            $hospitalAdmin = $this->user(
                'admin@basic-hospital.test', 'Basic Hospital Admin', Role::Admin, $hospital->id, $globalAdminPassword,
            );
            $doctor = $this->user(
                'doctor@basic-hospital.test', 'Dr Demo', Role::Doctor, $hospital->id, $globalAdminPassword, $medicine->id,
            );
            $this->user(
                'nurse@basic-hospital.test', 'Nurse Demo', Role::Nurse, $hospital->id, $globalAdminPassword, $medicine->id,
            );

            Patient::updateOrCreate(
                ['email' => 'patient@basic-hospital.test'],
                [
                    'hospital_number' => 'BASIC-00001',
                    'first_name' => 'Sample',
                    'last_name' => 'Patient',
                    'gender' => 'female',
                    'date_of_birth' => '1990-04-15',
                    'phone' => '08000000001',
                    'address' => 'Demo address, Enugu',
                    'blood_group' => 'O+',
                    'genotype' => 'AA',
                    'registration_paid_at' => now(),
                ],
            );

            $this->command?->info('SaaS demo ready: global admin '.$globalAdminEmail.'; basic hospital admin admin@basic-hospital.test.');
            $this->command?->line('Demo password: '.$globalAdminPassword);
            $this->command?->line('Basic hospital: '.$hospital->name.' ('.$hospital->slug.'); sample doctor ID '.$doctor->id.'.');
        });

        $context->set(null);
    }

    private function user(string $email, string $name, Role $role, int $hospitalId, string $password, ?int $departmentId = null): User
    {
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
        $user->password = $password;
        $user->is_global_admin = false;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
