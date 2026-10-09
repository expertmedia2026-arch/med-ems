<?php

namespace App\Http\Controllers;

use App\Models\HospitalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.hospital', ['settings' => HospitalSetting::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
            'website'        => ['nullable', 'string', 'max:150'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            // SVG is deliberately not allowed (it can carry scripts)
            'logo'           => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $settings = HospitalSetting::current();
        $disk     = Storage::disk('public');

        if (($request->boolean('remove_logo') || $request->hasFile('logo')) && $settings->logo_path) {
            $disk->delete($settings->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('hospital', 'public');
        }

        $settings->update(Arr::except($data, ['logo']));

        return redirect()->route('settings.edit')->with('success', 'Hospital settings saved.');
    }
}