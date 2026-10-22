<?php

namespace Database\Seeders;

use App\Models\HospitalSetting;
use Illuminate\Database\Seeder;

/** Configure predictable demo prices so the seeded A&E and mortuary invoices have billable lines. */
class DemoHospitalFeesSeeder extends Seeder
{
    public function run(): void
    {
        HospitalSetting::current()->update([
            'emergency_fee' => 2500,
            'mortuary_intake_fee' => 5000,
            'mortuary_daily_storage_fee' => 1500,
        ]);
    }
}
