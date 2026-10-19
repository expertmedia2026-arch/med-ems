<?php

namespace App\Services;

use App\Models\HospitalSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class HospitalSettingsService
{
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
    ): HospitalSetting
    {
        $settings = HospitalSetting::current();
        $disk = Storage::disk('public');
        $privateDisk = Storage::disk('local');
        $clearSecret = (bool) ($data['clear_paystack_secret_key'] ?? false);
        unset($data['clear_paystack_secret_key'], $data['remove_logo'], $data['remove_administrator_signature']);

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

        $settings->update(Arr::except($data, ['logo', 'administrator_signature']));

        return $settings->refresh();
    }
}
