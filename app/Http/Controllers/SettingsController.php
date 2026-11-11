<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHospitalSettingsRequest;
use App\Services\HospitalSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Renders current hospital settings and delegates validated updates to HospitalSettingsService.
 */
class SettingsController extends Controller
{
    /**
     * Validates the requested settings section and renders it with the current hospital configuration.
     */
    public function edit(Request $request, HospitalSettingsService $settings)
    {
        $section = $request->route('section') ?: 'basic';

        abort_unless(in_array($section, ['basic', 'payments', 'branding'], true), 404);
        abort_if($section === 'payments' && $request->user()?->hospital?->isStandalonePharmacy(), 404);

        return view('settings.hospital', ['settings' => $settings->current(), 'section' => $section]);
    }

    /**
     * Applies validated settings and optional file changes, then redirects to the saved settings section.
     */
    public function update(UpdateHospitalSettingsRequest $request, HospitalSettingsService $settings): RedirectResponse
    {
        $settings->update(
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
            $request->file('administrator_signature'),
            $request->boolean('remove_administrator_signature'),
        );

        $section = $request->route('section') ?: 'basic';

        return redirect()->route($section === 'basic' ? 'settings.edit' : 'settings.section', $section === 'basic' ? [] : ['section' => $section])
            ->with('success', 'Hospital settings saved.');
    }
}
