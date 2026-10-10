<?php

namespace Database\Seeders;

use App\Models\ConsultationType;
use App\Models\Department;
use Illuminate\Database\Seeder;

class ConsultationPricingSeeder extends Seeder
{
    public function run(): void
    {
        // Sample NGN amounts for demo data; existing prices are left unchanged on reruns.
        $pricing = [
            'General Medicine' => [
                ['General Consultation', 'Initial general medical assessment.', 5000],
                ['Follow-up Consultation', 'Review visit for an existing medical concern.', 3000],
            ],
            'Paediatrics' => [
                ['Paediatric Consultation', 'Medical assessment for a child.', 5000],
                ['Child Wellness Visit', 'Routine child health and wellness assessment.', 4000],
            ],
            'Obstetrics and Gynaecology' => [
                ['Antenatal Consultation', 'Antenatal assessment and pregnancy review.', 6000],
                ['Gynaecology Consultation', 'Assessment for a gynaecological concern.', 6000],
            ],
            'Surgery' => [
                ['Surgical Consultation', 'Assessment for a surgical concern.', 7500],
                ['Surgical Follow-up', 'Post-operative or surgical review visit.', 4000],
            ],
            'Dental' => [
                ['Dental Consultation', 'Initial oral health and dental assessment.', 5000],
                ['Dental Review', 'Follow-up dental review.', 3000],
            ],
            'Emergency' => [
                ['Emergency Consultation', 'Urgent medical assessment.', 10000],
                ['Emergency Review', 'Follow-up assessment after emergency care.', 6000],
            ],
        ];

        foreach ($pricing as $departmentName => $types) {
            $department = Department::firstOrCreate(['name' => $departmentName]);

            foreach ($types as [$name, $description, $price]) {
                ConsultationType::firstOrCreate(
                    ['department_id' => $department->id, 'name' => $name],
                    [
                        'description' => $description,
                        'price' => $price,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
