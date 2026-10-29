<?php

namespace App\Http\Controllers\GlobalAdmin;

use App\Models\Hospital;
use App\Services\GlobalAdminHospitalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Coordinates global-administrator hospital onboarding pages and operations.
 */
class HospitalOnboardingController
{
    private const MODULES = GlobalAdminHospitalService::MODULES;

    public function dashboard(GlobalAdminHospitalService $service): View
    {
        $hospitals = $service->hospitals();

        return view('global-admin.dashboard', [
            'hospitalCount' => $hospitals->count(),
            'staffCount' => $hospitals->sum('staff_count'),
            'patientCount' => $hospitals->sum('patient_count'),
            'hospitals' => $hospitals,
            'modules' => self::MODULES,
        ]);
    }

    public function index(GlobalAdminHospitalService $service): View
    {
        return view('global-admin.hospitals.index', [
            'hospitals' => $service->hospitals(),
            'modules' => self::MODULES,
        ]);
    }

    public function create(): View
    {
        return view('global-admin.hospitals.create', [
            'modules' => self::MODULES,
        ]);
    }

    public function configuration(GlobalAdminHospitalService $service): View
    {
        return view('global-admin.configuration', ['modules' => self::MODULES]);
    }

    public function edit(Hospital $hospital, GlobalAdminHospitalService $service): View
    {
        $hospital = $service->findHospital($hospital);

        return view('global-admin.hospitals.edit', [
            'hospital' => $hospital,
            'modules' => self::MODULES,
            'enabledModules' => $hospital->enabled_modules,
            'admissionTemplate' => $hospital->admission_template,
        ]);
    }

    public function store(Request $request, GlobalAdminHospitalService $service): RedirectResponse
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

        $result = $service->createHospital($data);
        $hospital = $result['hospital'];

        $status = Password::sendResetLink(['email' => $data['admin_email']]);

        if ($status === Password::RESET_LINK_SENT) {
            return redirect()->route('global-admin.hospitals.index')
                ->with('success', "{$hospital->name} was created. A password setup link was sent to {$data['admin_email']}.");
        }

        return redirect()->route('global-admin.hospitals.index')
            ->with('warning', "{$hospital->name} was created, but the password setup email could not be sent. Check mail configuration and resend the password reset link.");
    }

    public function update(
        Request $request,
        Hospital $hospital,
        GlobalAdminHospitalService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(self::MODULES))],
            'admission_template' => ['required', Rule::in(['current', 'basic'])],
        ]);

        $service->updateConfiguration($hospital, $data);

        return redirect()->route('global-admin.hospitals.index')->with('success', "Configuration for {$hospital->name} was updated.");
    }

    public function toggle(Hospital $hospital, GlobalAdminHospitalService $service): RedirectResponse
    {
        $hospital = $service->setHospitalActive($hospital, ! $hospital->is_active);

        $status = $hospital->is_active ? 'activated' : 'deactivated';

        return redirect()->route('global-admin.hospitals.index')
            ->with('success', "{$hospital->name} was {$status}.");
    }
}
