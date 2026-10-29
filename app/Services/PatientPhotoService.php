<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores patient profile photos on the private local storage disk.
 *
 * Replacing a photo updates the patient's stored path and removes the previous
 * stored file when one exists.
 */
class PatientPhotoService
{
    /**
     * Store a replacement photo and remove the previous file.
     *
     * A null upload is a no-op. The new file is stored before the old file is
     * deleted and the patient record is updated; storage changes are not part of a
     * database transaction.
     *
     * @param  Patient  $patient  Patient whose photo path should be updated.
     * @param  UploadedFile|null  $photo  Validated replacement upload, if supplied.
     */
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
