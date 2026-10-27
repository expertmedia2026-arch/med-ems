<?php

namespace App\Http\Controllers\GlobalAdmin;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\Patient;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HospitalOnboardingController
{
    public function __construct(private readonly HospitalContext $context) {}

    private const MODULES = [
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
        'lab_inventory' => 'Laboratory Inventory',
        'online_payments' => 'Online Payments',
        'finance_reports' => 'Finance Reports',
    ];

    public function dashboard(): View
    {
        $hospitals = $this->hospitalSummaries();
        $hospitalSizeChart = $hospitals->sortByDesc('patient_count')->take(6)->values();

        return view('global-admin.dashboard', [
            'hospitalCount' => $hospitals->count(),
            'staffCount' => $hospitals->sum('staff_count'),
            'patientCount' => $hospitals->sum('patient_count'),
            'hospitals' => $hospitals->take(5),
            'modules' => self::MODULES,
            'growthChart' => $this->growthChart(),
            'hospitalSizeChart' => $hospitalSizeChart,
        ]);
    }

    private function growthChart(): array
    {
        $firstMonth = now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn (int $offset) => $firstMonth->copy()->addMonths($offset));

        $hospitalMonthly = Hospital::query()->where('created_at', '>=', $firstMonth)->get(['created_at'])
            ->groupBy(fn ($hospital) => $hospital->created_at->format('Y-m'))->map->count();
        $hospitalCount = Hospital::query()->where('created_at', '<', $firstMonth)->count();
        $chart = ['labels' => [], 'hospitals' => []];

        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $hospitalCount += (int) $hospitalMonthly->get($key, 0);
            $chart['labels'][] = $month->format('M y');
            $chart['hospitals'][] = $hospitalCount;
        }

        return $chart;
    }

    public function index(): View
    {
        return view('global-admin.hospitals.index', [
            'hospitals' => $this->hospitalSummaries(),
            'modules' => self::MODULES,
        ]);
    }

    public function create(): View
    {
        return view('global-admin.hospitals.create', [
            'modules' => self::MODULES,
        ]);
    }

    public function configuration(): View
    {
        return view('global-admin.configuration', ['modules' => self::MODULES]);
    }

    private function hospitalSummaries()
    {
        $hospitals = Hospital::query()
            ->select('hospitals.*')
            ->addSelect([
                'staff_count' => User::withoutGlobalScope('hospital')->selectRaw('count(*)')
                    ->whereColumn('users.hospital_id', 'hospitals.id')
                    ->where('users.role', '!=', 'patient')
                    ->where('users.is_global_admin', false),
                'patient_count' => Patient::withoutGlobalScope('hospital')->selectRaw('count(*)')
                    ->whereColumn('patients.hospital_id', 'hospitals.id'),
            ])
            ->orderBy('name')
            ->get();

        $moduleSelections = \App\Models\HospitalModule::withoutGlobalScope('hospital')
            ->where('enabled', true)->get()->groupBy('hospital_id')
            ->map(fn ($modules) => $modules->pluck('module')->all());

        return $hospitals->each(fn ($hospital) => $hospital->setAttribute('enabled_modules', $moduleSelections->get($hospital->id, [])));
    }

    public function edit(Hospital $hospital): View
    {
        $enabledModules = \App\Models\HospitalModule::withoutGlobalScope('hospital')
            ->where('hospital_id', $hospital->id)->where('enabled', true)->pluck('module')->all();
        $workflow = \App\Models\HospitalWorkflow::withoutGlobalScope('hospital')
            ->where('hospital_id', $hospital->id)->where('workflow', 'admission')->where('is_active', true)
            ->orderByDesc('version')->first();

        return view('global-admin.hospitals.edit', [
            'hospital' => $hospital,
            'modules' => self::MODULES,
            'enabledModules' => $enabledModules,
            'admissionTemplate' => data_get($workflow?->definition, 'template', 'basic'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:hospitals,slug'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(self::MODULES))],
            'admission_template' => ['required', Rule::in(['current', 'basic'])],
        ]);

        $this->validateModuleDependencies($data['modules'] ?? []);

        $hospital = DB::transaction(function () use ($data): Hospital {
            $slug = $data['slug'] ?: Str::slug($data['name']);
            if (! $slug) {
                $slug = 'hospital';
            }

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

            $enabled = $data['modules'] ?? [];
            foreach (self::MODULES as $key => $label) {
                $hospital->modules()->create([
                    'module' => $key,
                    'enabled' => in_array($key, $enabled, true),
                ]);
            }

            $sections = [
                ['key' => 'admission_details', 'enabled' => true, 'required' => true],
                ['key' => 'vitals', 'enabled' => true, 'required' => true],
                ['key' => 'care_notes', 'enabled' => true, 'required' => false],
                ['key' => 'sugar_checks', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                ['key' => 'fluid_balance', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                ['key' => 'oxygen', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                ['key' => 'nebulizer', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                ['key' => 'insulin_machine', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
            ];

            HospitalWorkflow::create([
                'hospital_id' => $hospital->id,
                'workflow' => 'admission',
                'version' => 1,
                'definition' => [
                    'template' => $data['admission_template'],
                    'sections' => $sections,
                    'custom_fields' => [],
                ],
                'is_active' => true,
                'published_at' => now(),
            ]);

            return $hospital;
        });

        $status = Password::sendResetLink(['email' => $data['admin_email']]);

        if ($status === Password::RESET_LINK_SENT) {
            return redirect()->route('global-admin.hospitals.index')
                ->with('success', "{$hospital->name} was created. A password setup link was sent to {$data['admin_email']}.");
        }

        return redirect()->route('global-admin.hospitals.index')
            ->with('warning', "{$hospital->name} was created, but the password setup email could not be sent. Check mail configuration and resend the password reset link.");
    }

    public function update(Request $request, Hospital $hospital): RedirectResponse
    {
        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(self::MODULES))],
            'admission_template' => ['required', Rule::in(['current', 'basic'])],
        ]);

        $this->validateModuleDependencies($data['modules'] ?? []);

        $this->context->set((int) $hospital->id);
        try {
            DB::transaction(function () use ($data): void {
                $enabled = $data['modules'] ?? [];
                foreach (self::MODULES as $key => $label) {
                    $hospitalModule = \App\Models\HospitalModule::query()->firstOrNew(['module' => $key]);
                    $hospitalModule->enabled = in_array($key, $enabled, true);
                    $hospitalModule->save();
                }

                $sections = [
                    ['key' => 'admission_details', 'enabled' => true, 'required' => true],
                    ['key' => 'vitals', 'enabled' => true, 'required' => true],
                    ['key' => 'care_notes', 'enabled' => true, 'required' => false],
                    ['key' => 'sugar_checks', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                    ['key' => 'fluid_balance', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                    ['key' => 'oxygen', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                    ['key' => 'nebulizer', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                    ['key' => 'insulin_machine', 'enabled' => $data['admission_template'] === 'current', 'required' => false],
                ];
                $version = (int) \App\Models\HospitalWorkflow::query()->where('workflow', 'admission')->max('version') + 1;
                \App\Models\HospitalWorkflow::query()->where('workflow', 'admission')->where('is_active', true)->update(['is_active' => false]);
                HospitalWorkflow::create([
                    'workflow' => 'admission',
                    'version' => $version,
                    'definition' => ['template' => $data['admission_template'], 'sections' => $sections, 'custom_fields' => []],
                    'is_active' => true,
                    'published_at' => now(),
                ]);
            });
        } finally {
            $this->context->set(null);
        }

        return redirect()->route('global-admin.hospitals.index')->with('success', "Configuration for {$hospital->name} was updated.");
    }

    public function toggle(Hospital $hospital): RedirectResponse
    {
        $hospital->is_active = ! $hospital->is_active;
        $hospital->save();

        $status = $hospital->is_active ? 'activated' : 'deactivated';

        return redirect()->route('global-admin.hospitals.index')
            ->with('success', "{$hospital->name} was {$status}.");
    }

    private function validateModuleDependencies(array $enabled): void
    {
        $dependencies = [
            'external_lab' => ['laboratory'],
            'lab_inventory' => ['laboratory'],
            'emergency_pharmacy' => ['emergency', 'pharmacy'],
            'online_payments' => ['patient_portal'],
        ];

        foreach ($dependencies as $module => $requiredModules) {
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
}
