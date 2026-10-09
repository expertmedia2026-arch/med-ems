<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['Ngozi',   'Okafor',   'female', '1992-04-12'],
            ['Tunde',   'Adeyemi',  'male',   '1985-11-03'],
            ['Amina',   'Bello',    'female', '1998-07-21'],
            ['Chinedu', 'Eze',      'male',   '1979-02-14'],
            ['Funmi',   'Ogunleye', 'female', '2001-09-30'],
            ['Ibrahim', 'Musa',     'male',   '1966-05-18'],
            ['Blessing','Udoh',     'female', '2015-01-09'],
            ['Segun',   'Balogun',  'male',   '1990-12-25'],
            ['Halima',  'Yusuf',    'female', '1988-03-07'],
            ['Emeka',   'Nwosu',    'male',   '1973-08-16'],
        ];

        $blood     = ['A+', 'B+', 'O+', 'O-', 'AB+'];
        $genotypes = ['AA', 'AA', 'AA', 'AS'];

        foreach ($people as $i => [$first, $last, $gender, $dob]) {
            Patient::create([
                'first_name'    => $first,
                'last_name'     => $last,
                'gender'        => $gender,
                'date_of_birth' => $dob,
                'phone'         => '0803000' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'blood_group'   => $blood[$i % count($blood)],
                'genotype'      => $genotypes[$i % count($genotypes)],
            ]);
        }

        // A few appointments today for the demo
        $doctor = User::ofRole(Role::Doctor)->first();

        if ($doctor) {
            Patient::take(4)->get()->each(function (Patient $patient, int $i) use ($doctor) {
                Appointment::create([
                    'patient_id'   => $patient->id,
                    'doctor_id'    => $doctor->id,
                    'scheduled_at' => today()->setTime(9, 0)->addMinutes(30 * $i),
                    'reason'       => ['Fever and headache', 'Follow-up review', 'Abdominal pain', 'Routine check-up'][$i],
                ]);
            });
        }
    }
}