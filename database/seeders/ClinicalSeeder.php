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
            ['Paracetamol', 'Paracetamol', 'Tablet', '500mg', 50, 240, 20],
            ['Ibuprofen', 'Ibuprofen', 'Tablet', '400mg', 80, 180, 20],
            ['Diclofenac', 'Diclofenac sodium', 'Tablet', '50mg', 90, 120, 15],
            ['Amoxicillin', 'Amoxicillin', 'Capsule', '500mg', 150, 220, 20],
            ['Ciprofloxacin', 'Ciprofloxacin', 'Tablet', '500mg', 200, 150, 20],
            ['Metronidazole', 'Metronidazole', 'Tablet', '400mg', 60, 190, 20],
            ['Coartem', 'Artemether/Lumefantrine', 'Tablet', '80/480mg', 1800, 45, 10],
            ['Omeprazole', 'Omeprazole', 'Capsule', '20mg', 120, 160, 20],
            ['Metformin', 'Metformin', 'Tablet', '500mg', 70, 200, 20],
            ['Amlodipine', 'Amlodipine', 'Tablet', '5mg', 100, 110, 20],
            ['Loratadine', 'Loratadine', 'Tablet', '10mg', 90, 4, 10],
            ['Oral Rehydration Salts', 'Oral rehydration salts', 'Sachet', null, 250, 75, 10],
            ['Vitamin C', 'Ascorbic acid', 'Tablet', '100mg', 20, 320, 25],
            ['Ferrous Sulphate', 'Ferrous sulphate', 'Tablet', '200mg', 30, 170, 20],
            ['Artemether Injection', 'Artemether', 'Injection', '80mg/ml', 2200, 6, 10],
            ['Cetirizine', 'Cetirizine hydrochloride', 'Tablet', '10mg', 60, 130, 15],
        ];

        foreach ($drugs as [$name, $generic, $form, $strength, $price, $stock, $reorderLevel]) {
            Drug::updateOrCreate(
                ['name' => $name, 'strength' => $strength],
                [
                    'generic_name' => $generic,
                    'form' => $form,
                    'unit_price' => $price,
                    'stock_quantity' => $stock,
                    'reorder_level' => $reorderLevel,
                    'is_active' => true,
                ],
            );
        }

        $tests = [
            ['Malaria parasite (MP)', 'LAB001', 'Hematology', 'Blood', 6, 1500],
            ['Full blood count (FBC)', 'LAB002', 'Hematology', 'Blood', 8, 4000],
            ['Widal test', 'LAB003', 'Microbiology', 'Blood', 24, 2500],
            ['Urinalysis', 'LAB004', 'Clinical Chemistry', 'Urine', 4, 1500],
            ['Fasting blood sugar', 'LAB005', 'Clinical Chemistry', 'Blood', 4, 1000],
            ['Random blood sugar', 'LAB006', 'Clinical Chemistry', 'Blood', 4, 1000],
            ['Genotype', 'LAB007', 'Hematology', 'Blood', 48, 2000],
            ['Blood group', 'LAB008', 'Hematology', 'Blood', 24, 1500],
            ['Hepatitis B (HBsAg)', 'LAB009', 'Serology', 'Blood', 24, 3000],
            ['HIV screening', 'LAB010', 'Serology', 'Blood', 24, 2000],
            ['Pregnancy test', 'LAB011', 'Serology', 'Urine', 2, 1000],
            ['Stool microscopy', 'LAB012', 'Microbiology', 'Stool', 24, 1500],
            ['Lipid profile', 'LAB013', 'Clinical Chemistry', 'Blood', 24, 8000],
            ['Liver function test', 'LAB014', 'Clinical Chemistry', 'Blood', 24, 9000],
            ['Kidney function test', 'LAB015', 'Clinical Chemistry', 'Blood', 24, 9000],
            ['Thyroid function test', 'LAB016', 'Clinical Chemistry', 'Blood', 48, 7500],
            ['Electrolytes, urea and creatinine', 'LAB017', 'Clinical Chemistry', 'Blood', 8, 6500],
        ];

        foreach ($tests as [$name, $code, $category, $sampleType, $turnaround, $price]) {
            LabTest::updateOrCreate(
                ['name' => $name],
                [
                    'code' => $code,
                    'category' => $category,
                    'sample_type' => $sampleType,
                    'turnaround_hours' => $turnaround,
                    'description' => $name.' laboratory test.',
                    'price' => $price,
                    'is_active' => true,
                ],
            );
        }
    }
}
