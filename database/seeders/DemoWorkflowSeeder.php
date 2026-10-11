<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LabAttachment;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\PharmacyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DemoWorkflowSeeder extends Seeder
{
    private const LAB_PLANS = [
        9 => [['complete' => false, 'file' => false]],
        10 => [['complete' => false, 'file' => false]],
        12 => [['complete' => true, 'file' => false]],
        13 => [['complete' => true, 'file' => true]],
        14 => [['complete' => true, 'file' => false]],
        18 => [['complete' => true, 'file' => true], ['complete' => true, 'file' => false]],
        19 => [['complete' => false, 'file' => false]],
        20 => [['complete' => false, 'file' => false]],
        21 => [['complete' => false, 'file' => false]],
    ];

    public function run(): void
    {
        $patients = Patient::orderBy('id')->get();
        $departments = Department::orderBy('id')->get();
        $doctors = User::ofRole(Role::Doctor)->orderBy('department_id')->get()->groupBy('department_id');
        $nurses = User::ofRole(Role::Nurse)->orderBy('department_id')->get()->groupBy('department_id');
        $tests = LabTest::active()->orderBy('id')->get();
        $drugs = Drug::active()->orderBy('id')->get();

        $receptionist = User::where('email', 'reception@hms.test')->firstOrFail();
        $labStaff = User::where('email', 'lab@hms.test')->firstOrFail();
        $pharmacist = User::where('email', 'pharmacy@hms.test')->firstOrFail();
        $cashier = User::where('email', 'cashier@hms.test')->firstOrFail();

        $appointments = $this->seedAppointments($patients, $departments, $doctors, $tests, $receptionist);
        $visits = $this->seedVisits($patients, $departments, $doctors, $nurses, $tests, $drugs, $appointments, $receptionist);

        $billing = app(BillingService::class);
        $this->seedRegistrationBilling($patients, $billing, $cashier);
        $this->seedVisitBilling($visits, $billing, $cashier);
        $this->seedLabResults($visits, $labStaff);
        $this->seedDispensing($visits, $pharmacist);
    }

    private function seedAppointments(Collection $patients, Collection $departments, Collection $doctors, Collection $tests, User $receptionist): array
    {
        $appointments = [];
        $visitAppointmentStatuses = [
            ...array_fill(0, 12, AppointmentStatus::CheckedIn),
            ...array_fill(0, 6, AppointmentStatus::Completed),
        ];

        for ($i = 0; $i < 18; $i++) {
            $department = $departments[$i % $departments->count()];
            $doctor = $doctors[$department->id]->first();
            $appointments[$i] = Appointment::create([
                'patient_id' => $patients[$i]->id,
                'doctor_id' => $doctor->id,
                'type' => 'consultation',
                'scheduled_at' => now()->subHours(5)->addMinutes($i * 10),
                'reason' => ['Headache and fever', 'Follow-up review', 'Abdominal pain', 'Routine assessment'][$i % 4],
                'status' => $visitAppointmentStatuses[$i],
                'created_by' => $receptionist->id,
            ]);
        }

        for ($i = 0; $i < 8; $i++) {
            $department = $departments[$i % $departments->count()];
            $doctor = $doctors[$department->id]->first();
            Appointment::create([
                'patient_id' => $patients[30 + $i]->id,
                'doctor_id' => $doctor->id,
                'type' => 'consultation',
                'scheduled_at' => now()->addDays($i + 1)->setTime(9 + ($i % 6), 0),
                'reason' => 'Booked outpatient consultation',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $receptionist->id,
            ]);
        }

        for ($i = 0; $i < 4; $i++) {
            $appointment = Appointment::create([
                'patient_id' => $patients[40 + $i]->id,
                'doctor_id' => null,
                'type' => 'lab',
                'scheduled_at' => now()->addDays($i + 1)->setTime(10 + $i, 0),
                'reason' => 'Booked laboratory tests',
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $receptionist->id,
            ]);
            $appointment->labTests()->sync($tests->slice($i, 2)->modelKeys());
        }

        return $appointments;
    }

    private function seedVisits(
        Collection $patients,
        Collection $departments,
        Collection $doctors,
        Collection $nurses,
        Collection $tests,
        Collection $drugs,
        array $appointments,
        User $receptionist,
    ): Collection {
        $specs = [
            ['patient' => 0, 'status' => VisitStatus::WaitingTriage],
            ['patient' => 1, 'status' => VisitStatus::WaitingTriage],
            ['patient' => 2, 'status' => VisitStatus::WaitingTriage],
            ['patient' => 3, 'status' => VisitStatus::WaitingTriage],
            ['patient' => 4, 'status' => VisitStatus::WaitingTriage],
            ['patient' => 5, 'status' => VisitStatus::WaitingDoctor],
            ['patient' => 6, 'status' => VisitStatus::WaitingDoctor],
            ['patient' => 7, 'status' => VisitStatus::WaitingDoctor],
            ['patient' => 8, 'status' => VisitStatus::WaitingDoctor],
            ['patient' => 9, 'status' => VisitStatus::InConsultation],
            ['patient' => 10, 'status' => VisitStatus::InConsultation],
            ['patient' => 11, 'status' => VisitStatus::InConsultation],
            ['patient' => 12, 'status' => VisitStatus::Completed],
            ['patient' => 13, 'status' => VisitStatus::Completed],
            ['patient' => 14, 'status' => VisitStatus::Completed],
            ['patient' => 15, 'status' => VisitStatus::Completed],
            ['patient' => 16, 'status' => VisitStatus::Completed],
            ['patient' => 17, 'status' => VisitStatus::Completed],
            ['patient' => 18, 'status' => VisitStatus::Completed, 'type' => 'lab_only'],
            ['patient' => 19, 'status' => VisitStatus::LabOnly, 'type' => 'lab_only'],
            ['patient' => 20, 'status' => VisitStatus::LabOnly, 'type' => 'lab_only'],
            ['patient' => 79, 'status' => VisitStatus::LabOnly, 'type' => 'lab_only', 'external' => true],
            ['patient' => 21, 'status' => VisitStatus::WaitingTriage],
        ];

        $visits = collect();
        $labMetadata = [];
        $prescriptionsToDispense = [];
        $prescriptionsToPay = [];

        foreach ($specs as $index => $spec) {
            $patient = $patients[$spec['patient']];
            $department = $departments[$index % $departments->count()];
            $type = $spec['type'] ?? 'consultation';
            $doctor = $doctors[$department->id]->first();
            $consultationType = $type === 'consultation'
                ? ConsultationType::where('department_id', $department->id)->orderBy('id')->firstOrFail()
                : null;
            $status = $spec['status'];
            $checkedInAt = now()->subMinutes((count($specs) - $index) * 13);
            $isClinical = $type === 'consultation';

            $visit = Visit::create([
                'patient_id' => $patient->id,
                'appointment_id' => $index < 18 ? $appointments[$index]->id : null,
                'doctor_id' => $isClinical && $status !== VisitStatus::WaitingTriage ? $doctor->id : null,
                'department_id' => $isClinical ? $department->id : null,
                'consultation_type_id' => $consultationType?->id,
                'type' => $type,
                'status' => $status,
                'complaint' => ($spec['external'] ?? false) ? 'New patient requested an external laboratory test.' : ($isClinical ? 'Seeded outpatient visit.' : 'Laboratory investigation requested.'),
                'diagnosis' => $status === VisitStatus::Completed && $isClinical ? 'Acute uncomplicated infection; stable.' : null,
                'clinical_notes' => $status === VisitStatus::Completed && $isClinical ? 'Patient assessed and given a follow-up plan.' : null,
                'referred_from' => ($spec['external'] ?? false) ? 'External laboratory request' : null,
                'referred_by' => ($spec['external'] ?? false) ? 'New walk-in patient' : null,
                'checked_in_at' => $checkedInAt,
                'consulted_at' => $status === VisitStatus::InConsultation || $status === VisitStatus::Completed ? $checkedInAt->copy()->addMinutes(25) : null,
                'closed_at' => $status === VisitStatus::Completed ? $checkedInAt->copy()->addHours(2) : null,
            ]);
            $visits->put($index, $visit);

            if ($isClinical && $status !== VisitStatus::WaitingTriage) {
                $nurse = $nurses[$department->id]->first();
                $visit->vitals()->create([
                    'recorded_by' => $nurse->id,
                    'temperature' => $index % 2 === 0 ? 37.2 : 38.1,
                    'pulse' => 72 + ($index % 35),
                    'respiratory_rate' => 16 + ($index % 5),
                    'bp_systolic' => 112 + ($index % 28),
                    'bp_diastolic' => 72 + ($index % 14),
                    'spo2' => 96 + ($index % 4),
                    'weight' => 52 + ($index % 45),
                    'height' => 150 + ($index % 35),
                    'notes' => 'Seeded triage observations.',
                ]);
            }

            foreach (self::LAB_PLANS[$index] ?? [] as $labOffset => $plan) {
                $test = $tests[($index + $labOffset) % $tests->count()];
                $requesterId = $isClinical ? $doctor->id : $receptionist->id;
                $request = $visit->labRequests()->create([
                    'lab_test_id' => $test->id,
                    'requested_by' => $requesterId,
                    'status' => WorkStatus::Pending,
                ]);
                $labMetadata[$index][] = ['request' => $request, ...$plan];
            }

            foreach ($this->prescriptionPlan($index, $drugs, $doctor) as $prescriptionPlan) {
                $shouldDispense = $prescriptionPlan['seed_dispense'];
                $shouldPay = $prescriptionPlan['seed_paid'];
                unset($prescriptionPlan['seed_dispense'], $prescriptionPlan['seed_paid']);
                $prescription = $visit->prescriptions()->create($prescriptionPlan);
                if ($shouldDispense) {
                    $prescriptionsToDispense[$index][] = $prescription->id;
                }
                if ($shouldPay) {
                    $prescriptionsToPay[$index][] = $prescription->id;
                }
            }
        }

        $visits->each(function (Visit $visit, int $index) use ($labMetadata) {
            $visit->setAttribute('seed_lab_metadata', $labMetadata[$index] ?? []);
        });
        $visits->each(function (Visit $visit, int $index) use ($prescriptionsToDispense) {
            $visit->setAttribute('seed_dispense_ids', $prescriptionsToDispense[$index] ?? []);
        });
        $visits->each(function (Visit $visit, int $index) use ($prescriptionsToPay) {
            $visit->setAttribute('seed_paid_prescription_ids', $prescriptionsToPay[$index] ?? []);
        });

        return $visits;
    }

    private function prescriptionPlan(int $visitIndex, Collection $drugs, User $doctor): array
    {
        $prescription = match ($visitIndex) {
            9 => ['drug' => 7, 'quantity' => 12, 'seed_dispense' => false, 'seed_paid' => false],
            12 => ['drug' => 0, 'quantity' => 10, 'seed_dispense' => true, 'seed_paid' => true],
            13 => ['drug' => 3, 'quantity' => 12, 'seed_dispense' => true, 'seed_paid' => true],
            14 => ['drug' => 1, 'quantity' => 8, 'seed_dispense' => false, 'seed_paid' => true],
            15 => ['drug' => 6, 'quantity' => 6, 'seed_dispense' => false, 'seed_paid' => false],
            default => null,
        };

        if (! $prescription) {
            return [];
        }

        return [[
            'drug_id' => $drugs[$prescription['drug']]->id,
            'prescribed_by' => $doctor->id,
            'dosage' => '1 tablet',
            'frequency' => 'Twice daily',
            'duration' => '5 days',
            'quantity' => $prescription['quantity'],
            'instructions' => 'Take after food.',
            'status' => WorkStatus::Pending,
            'seed_dispense' => $prescription['seed_dispense'],
            'seed_paid' => $prescription['seed_paid'],
        ]];
    }

    private function seedRegistrationBilling(Collection $patients, BillingService $billing, User $cashier): void
    {
        foreach ($patients as $patient) {
            $patient->update(['registration_paid_at' => now()]);
            $invoice = $billing->registrationInvoiceFor($patient);

            if (! $invoice) {
                continue;
            }

            $items = $invoice->items()->where('category', 'registration')->whereNull('payment_id')->get();
            $this->settleItems($invoice, $items, $cashier, 'SEED-REG-'.$patient->id);
        }
    }

    private function seedVisitBilling(Collection $visits, BillingService $billing, User $cashier): void
    {
        foreach ($visits as $visit) {
            $billing->syncFor($visit);
            $invoice = Invoice::where('visit_id', $visit->id)->with('items')->firstOrFail();
            $metadata = collect($visit->getAttribute('seed_lab_metadata'));
            $completedLabIds = $metadata->where('complete', true)->pluck('request.id')->all();
            $prescriptionsToPay = $visit->getAttribute('seed_paid_prescription_ids');
            $payConsultation = $visit->isLabOnly() || in_array($visit->status, [VisitStatus::WaitingDoctor, VisitStatus::InConsultation, VisitStatus::Completed], true);

            $paidItems = $invoice->items->filter(function (InvoiceItem $item) use ($payConsultation, $completedLabIds) {
                if ($item->category === 'consultation') {
                    return $payConsultation;
                }

                if ($item->category === 'lab') {
                    return in_array((int) $item->billable_id, $completedLabIds, true);
                }

                if ($item->category === 'drug') {
                    return in_array((int) $item->billable_id, $prescriptionsToPay, true);
                }

                return false;
            });

            $this->settleItems($invoice, $paidItems, $cashier, 'SEED-VISIT-'.$visit->id);
        }
    }

    private function settleItems(Invoice $invoice, Collection $items, User $cashier, string $reference): ?Payment
    {
        $items = $items->whereNull('payment_id');
        $amount = (float) $items->sum('total');

        if ($items->isEmpty() || $amount <= 0) {
            return null;
        }

        $payment = $invoice->payments()->create([
            'amount' => $amount,
            'method' => ['cash', 'transfer', 'pos'][$invoice->id % 3],
            'reference' => $reference,
            'received_by' => $cashier->id,
        ]);
        $invoice->items()->whereIn('id', $items->modelKeys())->update(['payment_id' => $payment->id]);
        $invoice->recalculate();

        return $payment;
    }

    private function seedLabResults(Collection $visits, User $labStaff): void
    {
        foreach ($visits as $visit) {
            foreach ($visit->getAttribute('seed_lab_metadata') as $metadata) {
                if (! $metadata['complete']) {
                    continue;
                }

                $request = $metadata['request'];
                $request->update([
                    'result' => 'Result available. Findings reviewed by the laboratory scientist.',
                    'status' => WorkStatus::Completed,
                    'processed_by' => $labStaff->id,
                    'completed_at' => now()->subHours(2),
                ]);

                if (! $metadata['file']) {
                    continue;
                }

                $path = "lab-attachments/{$request->id}/seed-result.pdf";
                $contents = $this->resultPdf($request->test->name);
                Storage::disk('local')->put($path, $contents);

                LabAttachment::create([
                    'lab_request_id' => $request->id,
                    'path' => $path,
                    'original_name' => str_replace(['/', ' '], ['-', '_'], $request->test->name).'-result.pdf',
                    'mime' => 'application/pdf',
                    'size' => strlen($contents),
                    'uploaded_by' => $labStaff->id,
                ]);
            }
        }
    }

    private function resultPdf(string $testName): string
    {
        $escape = fn (string $text) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = 'BT /F1 16 Tf 50 750 Td (Hospital Laboratory Result) Tj /F1 11 Tf 0 -30 Td (Test: '.$escape($testName).') Tj 0 -22 Td (Result: Findings reviewed by the laboratory scientist.) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n% Seeded demo laboratory report\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function seedDispensing(Collection $visits, User $pharmacist): void
    {
        $pharmacy = app(PharmacyService::class);

        foreach ($visits as $visit) {
            $ids = $visit->getAttribute('seed_dispense_ids');
            if ($ids === [] || $visit->status !== VisitStatus::Completed) {
                continue;
            }

            $pharmacy->dispense($visit, $ids, $pharmacist->id);
        }
    }
}
