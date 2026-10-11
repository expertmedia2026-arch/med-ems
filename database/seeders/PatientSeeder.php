<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    private const PATIENTS = [
        ['Adaeze', 'female'], ['Chiamaka', 'female'], ['Ngozi', 'female'], ['Ifeoma', 'female'],
        ['Nneka', 'female'], ['Ugochi', 'female'], ['Amarachi', 'female'], ['Ogechi', 'female'],
        ['Grace', 'female'], ['Mary', 'female'], ['Chinedu', 'male'], ['Emeka', 'male'],
        ['Obinna', 'male'], ['Kelechi', 'male'], ['John', 'male'], ['David', 'male'],
    ];

    private const SURNAMES = ['Okafor', 'Nwankwo', 'Eze', 'Obi', 'Smith'];

    public function run(): void
    {
        $bloodGroups = ['A+', 'B+', 'O+', 'O-', 'AB+'];
        $genotypes = ['AA', 'AA', 'AS', 'AA', 'AC'];

        foreach (self::PATIENTS as $nameIndex => [$firstName, $gender]) {
            foreach (self::SURNAMES as $surnameIndex => $lastName) {
                $index = $nameIndex * count(self::SURNAMES) + $surnameIndex + 1;
                $isNhis = $index <= 56;
                $birthYear = 1945 + (($index * 7) % 65);
                $month = (($index - 1) % 12) + 1;
                $day = (($index * 3 - 1) % 28) + 1;

                Patient::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => $gender,
                    'date_of_birth' => sprintf('%04d-%02d-%02d', $birthYear, $month, $day),
                    'phone' => '081'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'email' => sprintf('patient%03d@hms.test', $index),
                    'address' => ['New Haven', 'Independence Layout', 'GRA', 'Trans-Ekulu', 'Umuahia Road'][($index - 1) % 5].', Enugu',
                    'blood_group' => $bloodGroups[($index - 1) % count($bloodGroups)],
                    'genotype' => $genotypes[($index - 1) % count($genotypes)],
                    'is_nhis' => $isNhis,
                    'nhis_number' => $isNhis ? 'NHIS-DEMO-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT) : null,
                ]);
            }
        }
    }
}
