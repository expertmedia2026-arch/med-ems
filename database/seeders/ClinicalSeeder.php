<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\LabTest;
use Illuminate\Database\Seeder;

class ClinicalSeeder extends Seeder
{
    public function run(): void
    {
        $drugs = [
            // name, generic, form, strength, price (₦), stock
            ['Paracetamol',  'Paracetamol',                'Tablet',    '500mg',     50,  500],
            ['Ibuprofen',    'Ibuprofen',                  'Tablet',    '400mg',     80,  300],
            ['Diclofenac',   'Diclofenac sodium',          'Tablet',    '50mg',      90,  200],
            ['Amoxicillin',  'Amoxicillin',                'Capsule',   '500mg',    150,  400],
            ['Ciprofloxacin','Ciprofloxacin',              'Tablet',    '500mg',    200,  250],
            ['Metronidazole','Metronidazole',              'Tablet',    '400mg',     60,  300],
            ['Coartem',      'Artemether/Lumefantrine',    'Tablet',    '80/480mg', 1800, 120],
            ['Omeprazole',   'Omeprazole',                 'Capsule',   '20mg',     120,  250],
            ['Metformin',    'Metformin',                  'Tablet',    '500mg',     70,  300],
            ['Amlodipine',   'Amlodipine',                 'Tablet',    '5mg',      100,  200],
            ['Loratadine',   'Loratadine',                 'Tablet',    '10mg',      90,  150],
            ['ORS',          'Oral rehydration salts',     'Sachet',    null,       250,  100],
            ['Vitamin C',    'Ascorbic acid',              'Tablet',    '100mg',     20,  600],
            ['Ferrous sulphate','Ferrous sulphate',        'Tablet',    '200mg',     30,  400],
            ['Lonart DS',    'Artemether/Lumefantrine',    'Tablet',    '80/480mg', 1500,    8], // low stock for the demo
        ];

        foreach ($drugs as [$name, $generic, $form, $strength, $price, $stock]) {
            Drug::updateOrCreate(
                ['name' => $name, 'strength' => $strength],
                ['generic_name' => $generic, 'form' => $form, 'unit_price' => $price, 'stock_quantity' => $stock]
            );
        }

        $tests = [
            'Malaria parasite (MP)'  => 1500,
            'Full blood count (FBC)' => 4000,
            'Widal test'             => 2500,
            'Urinalysis'             => 1500,
            'Fasting blood sugar'    => 1000,
            'Random blood sugar'     => 1000,
            'Genotype'               => 2000,
            'Blood group'            => 1500,
            'Hepatitis B (HBsAg)'    => 3000,
            'HIV screening'          => 2000,
            'Pregnancy test'         => 1000,
            'Stool microscopy'       => 1500,
            'Lipid profile'          => 8000,
            'Liver function test'    => 9000,
            'Kidney function test'   => 9000,
        ];

        foreach ($tests as $name => $price) {
            LabTest::updateOrCreate(['name' => $name], ['price' => $price]);
        }
    }
}