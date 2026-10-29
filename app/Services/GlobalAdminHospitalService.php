<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\Patient;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates platform-level hospital onboarding and configuration.
 */
class GlobalAdminHospitalService
{
    public const MODULES = [
        'pharmacy' => 'Pharmacy',
        'mortuary' => 'Mortuary',
        'emergency' => 'A&E',
        'inpatient' => 'Inpatient admissions',
        'laboratory' => 'Laboratory',
        'external_lab' => 'External lab referrals',
        'staff_roster' => 'Staff duty roster',
        'appointments' => 'Appointments',
        'patient_id_card' => 'Patient ID cards',
        'patient_portal' => 'Patient Portal',
        'central_vitals' => 'Central Vitals',
        'medication_administration' => 'Medication Administration',
        'emergency_pharmacy' => 'A&E Pharmacy',
        'maternity' => 'Maternity',
        'lab_inventory' => 'Laboratory Inventory',
        'online_payments' => 'Online Payments',
        'finance_reports' => 'Finance Reports',
    ];

    private const MODULE_DEPENDENCIES = [
        'external_lab' => ['laboratory'],
        'lab_inventory' => ['laboratory'],
        'emergency_pharmacy' => ['emergency', 'pharmacy'],
        'maternity' => ['inpatient'],
        'online_payments' => ['patient_portal'],
    ];

    public function __construct(private readonly HospitalContext $context) {}

    /**
     * Return tenant-wide totals for the platform dashboard.
     *
     * @return array{hospital_count: int, active_hospital_count: int, staff_count: int, patient_count: int}
     */
    public function dashboard(): array
    {
        $hospitals = Hospital::query()->get(['id', 'is_active']);
        $hospitalIds = $hospitals->modelKeys();

        return [
            'hospital_count' => $hospitals->count(),
            'active_hospital_count' => $hospitals->where('is_active', true)->count(),
            'staff_count' => User::query()->whereIn('hospital_id', $hospitalIds)
                ->where('role', '!=', 'patient')
                ->where('is_global_admin', false)
                ->count(),
            'patient_count' => Patient::query()->whereIn('hospital_id', $hospitalIds)->count(),
        ];
    }

    /**
     * List hospitals with enabled modules, staff totals, patient totals, and active admin emails.
     *
     * @return Collection<int, Hospital>
     */
    public function hospitals(): Collection
    {
        $hospitals = Hospital::query()
            ->select('hospitals.*')
            ->addSelect([
                'staff_count' => User::query()->selectRaw('count(*)')
                    ->whereColumn('users.hospital_id', 'hospitals.id')
                    ->where('users.role', '!=', 'patient')
                    ->where('users.is_global_admin', false),
                'patient_count' => Patient::query()->selectRaw('count(*)')
                    ->whereColumn('patients.hospital_id', 'hospitals.id'),
            ])
            ->orderBy('name')
            ->get();

        $hospitalIds = $hospitals->modelKeys();
        $enabledModules = HospitalModule::query()
            ->whereIn('hospital_id', $hospitalIds)
            ->where('enabled', true)
            ->get()
            ->groupBy('hospital_id')
            ->map(fn ($modules) => $modules->pluck('module')->values()->all());
        $adminEmails = User::query()
            ->whereIn('hospital_id', $hospitalIds)
            ->where('role', 'admin')
            ->where('is_global_admin', false)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['hospital_id', 'email'])
            ->groupBy('hospital_id')
            ->map(fn ($admins) => $admins->pluck('email')->unique()->values()->all());
        $workflows = HospitalWorkflow::query()
            ->whereIn('hospital_id', $hospitalIds)
            ->where('workflow', 'admission')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->get()
            ->unique('hospital_id')
            ->keyBy('hospital_id');

        return $hospitals->each(function (Hospital $hospital) use ($enabledModules, $adminEmails, $workflows): void {
            $hospital->setAttribute('enabled_modules', $enabledModules->get($hospital->id, []));
            $hospital->setAttribute('admin_emails', $adminEmails->get($hospital->id, []));
            $hospital->setAttribute(
                'admission_template',
                data_get($workflows->get($hospital->id)?->definition, 'template', 'basic'),
            );
        });
    }

    /**
     * Return the requested hospital with the same summary fields as the list endpoint.
     */
    public function findHospital(Hospital $hospital): Hospital
    {
        return $this->hospitals()->firstWhere('id', $hospital->id)
            ?? abort(404);
    }

    /**
     * Provision a hospital, its initial administrator, settings, modules, and admission workflow.
     *
     * The administrator receives a random temporary password and must use the
     * password-reset link returned by the API to set their own credential.
     *
     * @param  array{name: string, slug?: string|null, admin_name: string, admin_email: string, modules?: array<int, string>, admission_template: string}  $data
     * @return array{hospital: Hospital, admin_email: string}
     */
    public function createHospital(array $data): array
    {
        $this->validateModuleDependencies($data['modules'] ?? []);

        $previousHospitalId = $this->context->id();

        try {
            $hospital = DB::transaction(function () use ($data): Hospital {
                $slug = ($data['slug'] ?? null) ?: Str::slug($data['name']);
                $slug = $slug ?: 'hospital';
                $baseSlug = $slug;
                $suffix = 2;

                while (Hospital::query()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$suffix++;
                }

                $hospital = Hospital::create([
                    'name' => $data['name'],
                    'slug' => $slug,
                    'is_active' => true,
                ]);
                $this->context->set((int) $hospital->id);

                $admin = new User;
                $admin->name = $data['admin_name'];
                $admin->email = $data['admin_email'];
                $admin->role = 'admin';
                $admin->password = Str::random(48);
                $admin->hospital_id = $hospital->id;
                $admin->is_active = true;
                $admin->save();

                HospitalSetting::create([
                    'hospital_id' => $hospital->id,
                    'name' => $hospital->name,
                ]);

                $enabledModules = $data['modules'] ?? [];
                foreach (self::MODULES as $key => $label) {
                    $hospital->modules()->create([
                        'module' => $key,
                        'enabled' => in_array($key, $enabledModules, true),
                    ]);
                }

                HospitalWorkflow::create([
                    'hospital_id' => $hospital->id,
                    'workflow' => 'admission',
                    'version' => 1,
                    'definition' => $this->admissionWorkflow($data['admission_template']),
                    'is_active' => true,
                    'published_at' => now(),
                ]);

                return $hospital;
            });
        } finally {
            $this->context->set($previousHospitalId);
        }

        return [
            'hospital' => $this->findHospital($hospital),
            'admin_email' => $data['admin_email'],
        ];
    }

    /**
     * Replace enabled modules and publish a new admission workflow version.
     *
     * @param  array{modules?: array<int, string>, admission_template: string}  $data
     */
    public function updateConfiguration(Hospital $hospital, array $data): Hospital
    {
        $this->validateModuleDependencies($data['modules'] ?? []);
        $previousHospitalId = $this->context->id();

        try {
            $this->context->set((int) $hospital->id);

            DB::transaction(function () use ($data): void {
                $enabledModules = $data['modules'] ?? [];
                foreach (self::MODULES as $key => $label) {
                    $module = HospitalModule::query()->firstOrNew(['module' => $key]);
                    $module->enabled = in_array($key, $enabledModules, true);
                    $module->save();
                }

                $version = (int) HospitalWorkflow::query()
                    ->where('workflow', 'admission')
                    ->max('version') + 1;
                HospitalWorkflow::query()
                    ->where('workflow', 'admission')
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                HospitalWorkflow::create([
                    'workflow' => 'admission',
                    'version' => $version,
                    'definition' => $this->admissionWorkflow($data['admission_template']),
                    'is_active' => true,
                    'published_at' => now(),
                ]);
            });
        } finally {
            $this->context->set($previousHospitalId);
        }

        return $this->findHospital($hospital);
    }

    /** Set the hospital's active state to an explicit value so repeated requests are idempotent. */
    public function setHospitalActive(Hospital $hospital, bool $isActive): Hospital
    {
        $hospital->update(['is_active' => $isActive]);

        return $this->findHospital($hospital);
    }

    /**
     * Return the platform's module catalog with dependency metadata for client forms.
     *
     * @return array<int, array{key: string, label: string, requires: array<int, string>}>
     */
    public function moduleCatalog(): array
    {
        return collect(self::MODULES)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'requires' => self::MODULE_DEPENDENCIES[$key] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Ensure selected modules include every prerequisite required by dependent modules.
     *
     * @param  array<int, string>  $enabled
     */
    public function validateModuleDependencies(array $enabled): void
    {
        foreach (self::MODULE_DEPENDENCIES as $module => $requiredModules) {
            if (! in_array($module, $enabled, true)) {
                continue;
            }

            foreach ($requiredModules as $requiredModule) {
                if (! in_array($requiredModule, $enabled, true)) {
                    throw ValidationException::withMessages([
                        'modules' => self::MODULES[$module].' requires '.self::MODULES[$requiredModule].'.',
                    ]);
                }
            }
        }
    }

    /**
     * Build the standard admission workflow definition used for new versions.
     *
     * @return array{template: string, sections: array<int, array{key: string, enabled: bool, required: bool}>, custom_fields: array}
     */
    private function admissionWorkflow(string $template): array
    {
        $sections = [
            ['key' => 'admission_details', 'enabled' => true, 'required' => true],
            ['key' => 'vitals', 'enabled' => true, 'required' => true],
            ['key' => 'care_notes', 'enabled' => true, 'required' => false],
        ];

        foreach (['sugar_checks', 'fluid_balance', 'oxygen', 'nebulizer', 'insulin_machine'] as $section) {
            $sections[] = [
                'key' => $section,
                'enabled' => $template === 'current',
                'required' => false,
            ];
        }

        return [
            'template' => $template,
            'sections' => $sections,
            'custom_fields' => [],
        ];
    }
}
