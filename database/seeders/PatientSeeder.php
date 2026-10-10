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
        $firstNames = [
            ['Adaeze', 'female'], ['Chiamaka', 'female'], ['Ngozi', 'female'],
            ['Ifeoma', 'female'], ['Nneka', 'female'], ['Ugochi', 'female'],
            ['Amarachi', 'female'], ['Chinedu', 'male'], ['Emeka', 'male'],
            ['Obinna', 'male'], ['Kelechi', 'male'], ['Nnamdi', 'male'],
            ['Chibuzo', 'male'], ['Ifeanyi', 'male'],
        ];
        $surnames = [
            'Okafor', 'Nwankwo', 'Eze', 'Obi', 'Okeke',
            'Nwosu', 'Madu', 'Umeh', 'Anyanwu', 'Nwachukwu',
        ];
        $bloodGroups = ['A+', 'B+', 'O+', 'O-', 'AB+'];
        $genotypes = ['AA', 'AA', 'AA', 'AS'];
        $patients = [];
        $index = 0;

        // 14 first names × 10 surnames = 140 repeatable, predominantly Igbo demo patients.
        foreach ($firstNames as [$firstName, $gender]) {
            foreach ($surnames as $lastName) {
                $index++;
                $month = (($index - 1) % 12) + 1;
                $day = (($index - 1) % 28) + 1;
                $birthYear = 1965 + (($index - 1) % 45);
                $dateOfBirth = sprintf('%04d-%02d-%02d', $birthYear, $month, $day);
                $phone = '080700'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);

                $patients[] = Patient::updateOrCreate(
                    ['phone' => $phone],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'gender' => $gender,
                        'date_of_birth' => $dateOfBirth,
                        'blood_group' => $bloodGroups[($index - 1) % count($bloodGroups)],
                        'genotype' => $genotypes[($index - 1) % count($genotypes)],
                    ],
                );
            }
        }

        // A few appointments today for the demo
        $doctor = User::ofRole(Role::Doctor)->orderBy('id')->first();

        if ($doctor) {
            foreach (array_slice($patients, 0, 4) as $i => $patient) {
                $scheduledAt = today()->setTime(9, 0)->addMinutes(30 * $i);

                Appointment::updateOrCreate(
                    ['patient_id' => $patient->id, 'scheduled_at' => $scheduledAt],
                    [
                        'doctor_id' => $doctor->id,
                        'reason' => ['Fever and headache', 'Follow-up review', 'Abdominal pain', 'Routine check-up'][$i],
                    ],
                );
            }
        }
    }
}
