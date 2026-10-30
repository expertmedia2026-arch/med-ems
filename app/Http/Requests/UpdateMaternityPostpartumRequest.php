<?php

namespace App\Http\Requests;

use App\Models\MaternityEpisode;
use App\Services\MaternityService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaternityPostpartumRequest extends FormRequest
{
    /** Reject staff outside the episode's admission scope before validating clinical data. */
    public function authorize(MaternityService $maternity): bool
    {
        $episode = $this->route('maternityEpisode');
        $user = $this->user();

        return $episode instanceof MaternityEpisode
            && $user !== null
            && $maternity->canAccessAdmission($episode->admission, $user);
    }

    /** Require a bounded written summary for the postpartum stage. */
    public function rules(): array
    {
        return [
            'postpartum_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
