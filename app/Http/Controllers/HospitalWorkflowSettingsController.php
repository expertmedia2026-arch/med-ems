<?php

namespace App\Http\Controllers;

use App\Models\HospitalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HospitalWorkflowSettingsController extends Controller
{
    private const OPTIONAL_SECTIONS = [
        'sugar_checks' => 'Blood sugar checks',
        'fluid_balance' => 'Fluid intake and output',
        'oxygen' => 'Oxygen chart',
        'nebulizer' => 'Nebulizer chart',
        'insulin_machine' => 'Insulin machine chart',
    ];

    public function edit(): View
    {
        $workflow = HospitalWorkflow::query()->where('workflow', 'admission')->where('is_active', true)->latest('version')->first();
        $enabled = collect(data_get($workflow?->definition, 'sections', []))
            ->filter(fn ($section) => ($section['enabled'] ?? false) === true)
            ->pluck('key')->all();

        return view('settings.admission-workflow', [
            'sections' => self::OPTIONAL_SECTIONS,
            'enabledSections' => $enabled,
            'version' => $workflow?->version ?? 0,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', Rule::in(array_keys(self::OPTIONAL_SECTIONS))],
        ]);

        DB::transaction(function () use ($data): void {
            $current = HospitalWorkflow::query()
                ->where('workflow', 'admission')->where('is_active', true)
                ->latest('version')->lockForUpdate()->first();

            $version = (int) HospitalWorkflow::query()->where('workflow', 'admission')->max('version') + 1;
            $template = data_get($current?->definition, 'template', 'current');
            $enabled = $data['sections'] ?? [];
            $sections = [
                ['key' => 'admission_details', 'enabled' => true, 'required' => true],
                ['key' => 'vitals', 'enabled' => true, 'required' => true],
                ['key' => 'care_notes', 'enabled' => true, 'required' => false],
            ];

            foreach (self::OPTIONAL_SECTIONS as $key => $label) {
                $sections[] = ['key' => $key, 'enabled' => in_array($key, $enabled, true), 'required' => false];
            }

            HospitalWorkflow::query()->where('workflow', 'admission')->where('is_active', true)->update(['is_active' => false]);
            HospitalWorkflow::create([
                'workflow' => 'admission',
                'version' => $version,
                'definition' => ['template' => $template, 'sections' => $sections, 'custom_fields' => []],
                'is_active' => true,
                'published_at' => now(),
            ]);
        });

        return redirect()->route('settings.admission-workflow')->with('success', 'Admission workflow saved as a new version. Existing clinical records were kept.');
    }
}
