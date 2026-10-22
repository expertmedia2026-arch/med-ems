<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ResetDemoDataSeeder::class);
        $this->call(DepartmentStaffSeeder::class);
        $this->call(ConsultationPricingSeeder::class);
        $this->call(PatientSeeder::class);
        $this->call(ClinicalSeeder::class);
        $this->call(DemoWorkflowSeeder::class);
        $this->call(EmergencyMortuarySeeder::class);
        $this->call(EmergencyPharmacySeeder::class);
    }
}
