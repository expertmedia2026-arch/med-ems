<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReassignment extends Model
{
    protected $guarded = [];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function fromDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_doctor_id');
    }

    public function toDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_doctor_id');
    }

    public function reassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_by');
    }
}
