<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PatientPhotoService
{
    public function replace(Patient $patient, ?UploadedFile $photo): void
    {
        if (! $photo) {
            return;
        }

        $disk = Storage::disk('local');
        $path = $photo->store('patient-photos', 'local');

        if ($patient->photo_path) {
            $disk->delete($patient->photo_path);
        }

        $patient->update(['photo_path' => $path]);
    }
}
