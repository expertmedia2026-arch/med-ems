<?php

namespace App\Http\Controllers;

use App\Models\HospitalSetting;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PatientMediaController extends Controller
{
    public function photo(Request $request, Patient $patient)
    {
        $user = $request->user();
        $isOwnPortal = $user->hasRole('patient') && $user->patient_id === $patient->id;
        $isAuthorizedStaff = $user->hasRole('admin', 'receptionist', 'nurse', 'doctor');
        abort_unless($isOwnPortal || $isAuthorizedStaff, 403);
        abort_unless($patient->photo_path && Storage::disk('local')->exists($patient->photo_path), 404);

        return Storage::disk('local')->response($patient->photo_path);
    }

    public function administratorSignature()
    {
        $path = HospitalSetting::current()->administrator_signature_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
