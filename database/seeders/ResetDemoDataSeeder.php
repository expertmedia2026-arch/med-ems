<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Clear HMS application records before the deterministic demo dataset is rebuilt. */
class ResetDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'online_payments',
            'personal_access_tokens',
            'patient_invitations',
            'staff_invitations',
            'lab_attachments',
            'vitals',
            'invoice_items',
            'payments',
            'invoices',
            'lab_requests',
            'prescriptions',
            'visits',
            'appointment_lab_test',
            'appointments',
            'doctor_availabilities',
            'consultation_types',
            'patients',
            'users',
            'departments',
            'drugs',
            'lab_tests',
        ] as $table) {
            DB::table($table)->delete();
        }
    }
}
