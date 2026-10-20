<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHospitalSettingsRequest;
use App\Services\HospitalSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request, HospitalSettingsService $settings)
    {
        $section = $request->route('section') ?: 'basic';

        abort_unless(in_array($section, ['basic', 'payments', 'branding'], true), 404);

        return view('settings.hospital', ['settings' => $settings->current(), 'section' => $section]);
    }

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
