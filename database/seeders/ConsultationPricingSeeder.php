<?php

namespace Database\Seeders;

use App\Models\ConsultationType;
use App\Models\Department;
use Illuminate\Database\Seeder;

class ConsultationPricingSeeder extends Seeder
{
    private const PRICING = [
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
        'Dental' => [
            ['Dental Consultation', 'Initial oral health and dental assessment.', 5000],
            ['Dental Review', 'Follow-up dental review.', 3000],
        ],
        'Cardiology' => [
            ['Cardiology Consultation', 'Initial heart and circulation assessment.', 8000],
            ['Cardiology Follow-up', 'Review visit for a heart or circulation concern.', 5000],
        ],
        'Dermatology' => [
            ['Dermatology Consultation', 'Initial skin health assessment.', 6500],
            ['Dermatology Review', 'Follow-up skin health review.', 4000],
        ],
        'Ear, Nose and Throat' => [
            ['ENT Consultation', 'Initial ear, nose, and throat assessment.', 6500],
            ['ENT Follow-up', 'Review visit for an ear, nose, or throat concern.', 4000],
        ],
        'Ophthalmology' => [
            ['Eye Consultation', 'Initial eye and vision assessment.', 6000],
            ['Eye Follow-up', 'Follow-up eye and vision review.', 3500],
        ],
    ];

    public function run(): void
    {
        foreach (self::PRICING as $departmentName => $types) {
            $department = Department::where('name', $departmentName)->firstOrFail();

            foreach ($types as [$name, $description, $price]) {
                ConsultationType::updateOrCreate(
                    ['department_id' => $department->id, 'name' => $name],
                    ['description' => $description, 'price' => $price, 'is_active' => true],
                );
            }
        }
    }
}
