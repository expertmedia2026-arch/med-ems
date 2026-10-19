<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHospitalSettingsRequest;
use App\Services\HospitalSettingsService;

class SettingsController extends Controller
{
    public function edit(HospitalSettingsService $settings)
    {
        return view('settings.hospital', ['settings' => $settings->current()]);
    }

    public function update(UpdateHospitalSettingsRequest $request, HospitalSettingsService $settings)
    {
        $settings->update(
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
            $request->file('administrator_signature'),
            $request->boolean('remove_administrator_signature'),
        );

        return redirect()->route('settings.edit')->with('success', 'Hospital settings saved.');
    }
}
