<?php

namespace App\Services;

use App\Models\HospitalSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HospitalSettingsService
{
    public function __construct(private readonly GlobalAdminNotificationService $globalAdminNotifications) {}

    public function current(): HospitalSetting
    {
        return HospitalSetting::current();
    }

    public function update(
        array $data,
        ?UploadedFile $logo,
        bool $removeLogo,
        ?UploadedFile $administratorSignature,
        bool $removeAdministratorSignature,
    ): HospitalSetting {
        $settings = HospitalSetting::current();
        $disk = Storage::disk('public');
        $privateDisk = Storage::disk('local');
        $clearSecret = (bool) ($data['clear_paystack_secret_key'] ?? false);
        $useAccent = (bool) ($data['use_accent_color'] ?? false);
        $useHighlight = (bool) ($data['use_highlight_color'] ?? false);
        $hasAccentToggle = array_key_exists('use_accent_color', $data);
        $hasHighlightToggle = array_key_exists('use_highlight_color', $data);
        unset($data['clear_paystack_secret_key'], $data['remove_logo'], $data['remove_administrator_signature']);
        unset($data['use_accent_color'], $data['use_highlight_color']);

        if (filled($data['primary_color'] ?? null)) {
            $data['primary_color'] = strtolower($data['primary_color']);
        }
        if (filled($data['secondary_color'] ?? null)) {
            $data['secondary_color'] = strtolower($data['secondary_color']);
        }
        if ($hasAccentToggle) {
            $data['accent_color'] = $useAccent && filled($data['accent_color'] ?? null)
                ? strtolower($data['accent_color'])
                : null;
        } elseif (filled($data['accent_color'] ?? null)) {
            $data['accent_color'] = strtolower($data['accent_color']);
        }
        if ($hasHighlightToggle) {
            $data['highlight_color'] = $useHighlight && filled($data['highlight_color'] ?? null)
                ? strtolower($data['highlight_color'])
                : null;
        } elseif (filled($data['highlight_color'] ?? null)) {
            $data['highlight_color'] = strtolower($data['highlight_color']);
        }

        if ($clearSecret) {
            $data['paystack_secret_key'] = null;
        } elseif (blank($data['paystack_secret_key'] ?? null)) {
            unset($data['paystack_secret_key']);
        }

        if (($removeLogo || $logo) && $settings->logo_path) {
            $disk->delete($settings->logo_path);
            $data['logo_path'] = null;
        }

        if ($logo) {
            $data['logo_path'] = $logo->store('hospital', 'public');
        }

        if (($removeAdministratorSignature || $administratorSignature) && $settings->administrator_signature_path) {
            $privateDisk->delete($settings->administrator_signature_path);
            $data['administrator_signature_path'] = null;
        }

        if ($administratorSignature) {
            $data['administrator_signature_path'] = $administratorSignature->store('hospital-signatures', 'local');
        }

        $previousName = $settings->name;
        $hospital = null;

        DB::transaction(function () use ($settings, $data, $previousName, &$hospital): void {
            $settings->update(Arr::except($data, ['logo', 'administrator_signature']));

            if ($previousName !== $settings->name) {
                $hospital = $settings->hospital()->firstOrFail();
                $hospital->update(['name' => $settings->name]);
            }
        });

        if ($hospital) {
            $this->globalAdminNotifications->hospitalNameChanged($hospital, $previousName, $settings->name);
        }

        return $settings->refresh();
    }
}
