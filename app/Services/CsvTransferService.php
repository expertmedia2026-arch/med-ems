<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\Drug;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvTransferService
{
    private const HEADERS = [
        'patients' => ['hospital_number', 'first_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'blood_group', 'genotype', 'allergies', 'next_of_kin_name', 'next_of_kin_phone', 'is_nhis', 'nhis_number', 'registration_paid_at'],
        'departments' => ['name', 'description'],
        'staff' => ['name', 'email', 'role', 'department_name'],
        'consultation-prices' => ['department_name', 'name', 'description', 'price', 'is_active'],
        'lab-catalog' => ['department_name', 'name', 'code', 'category', 'sample_type', 'price', 'turnaround_hours', 'description', 'is_active'],
        'pharmacy-catalog' => ['name', 'generic_name', 'form', 'strength', 'unit_price', 'stock_quantity', 'reorder_level', 'is_active'],
    ];

    public function __construct(
        private readonly DrugCatalogService $drugs,
        private readonly StaffInvitationService $staffInvitations,
    ) {}

    public function headers(string $dataset): array
    {
        abort_unless(isset(self::HEADERS[$dataset]), 404);

        return self::HEADERS[$dataset];
    }

    public function export(string $dataset, bool $template = false, ?User $actor = null): StreamedResponse
    {
        $headers = $this->headers($dataset);
        $filename = $dataset.($template ? '-template' : '-export').'.csv';

        return response()->streamDownload(function () use ($dataset, $headers, $template, $actor) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            if (! $template) {
                foreach ($this->records($dataset, $actor) as $record) {
                    fputcsv($output, array_map(fn ($header) => $record[$header] ?? '', $headers));
                }
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{added:int, skipped:int, errors:array<int,string>} */
    public function import(string $dataset, UploadedFile $file, User $actor): array
    {
        $headers = $this->headers($dataset);
        $handle = fopen($file->getRealPath(), 'r');
        $csvHeaders = fgetcsv($handle);

        if (! is_array($csvHeaders)) {
            fclose($handle);

            return ['added' => 0, 'skipped' => 0, 'errors' => ['The CSV file is empty.']];
        }

        $csvHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $csvHeaders[0]);
        $csvHeaders = array_map(fn ($value) => strtolower(trim((string) $value)), $csvHeaders);
        $missing = array_values(array_diff($headers, $csvHeaders));

        if ($missing) {
            fclose($handle);

            return ['added' => 0, 'skipped' => 0, 'errors' => ['Missing required columns: '.implode(', ', $missing).'. Download the CSV template and use its column names.']];
        }

        $added = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($csvHeaders as $index => $header) {
                $value = isset($values[$index]) ? trim((string) $values[$index]) : '';
                $row[$header] = $value === '' ? null : $value;
            }

            try {
                $result = DB::transaction(fn () => $this->importRow($dataset, $row, $actor));
                $result === 'added' ? $added++ : $skipped++;
            } catch (\Throwable $exception) {
                report($exception);
                if (count($errors) < 50) {
                    $errors[] = "Row {$line}: ".$this->friendlyError($exception);
                }
            }
        }

        fclose($handle);

        return compact('added', 'skipped', 'errors');
    }

    private function importRow(string $dataset, array $row, User $actor): string
    {
        return match ($dataset) {
            'patients' => $this->importPatient($row),
            'departments' => $this->importDepartment($row),
            'staff' => $this->importStaff($row, $actor),
            'consultation-prices' => $this->importConsultationPrice($row),
            'lab-catalog' => $this->importLabTest($row, $actor),
            'pharmacy-catalog' => $this->importDrug($row),
        };
    }

    private function importPatient(array $row): string
    {
        $data = $this->validate($row, [
            'hospital_number' => ['nullable', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'genotype' => ['nullable', Rule::in(['AA', 'AS', 'SS', 'AC', 'SC'])],
            'allergies' => ['nullable', 'string', 'max:500'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
            'is_nhis' => ['nullable', 'in:0,1'],
            'nhis_number' => ['nullable', 'string', 'max:50'],
            'registration_paid_at' => ['nullable', 'date'],
        ]);
        Validator::make($data, ['nhis_number' => ['required_if:is_nhis,1']])->validate();

        if (filled($data['hospital_number'] ?? null) && Patient::where('hospital_number', $data['hospital_number'])->exists()) {
            return 'skipped';
        }
        if (filled($data['email'] ?? null) && Patient::where('email', $data['email'])->exists()) {
            return 'skipped';
        }

        $data['hospital_number'] = filled($data['hospital_number'] ?? null) ? $data['hospital_number'] : Patient::nextHospitalNumber();
        $data['is_nhis'] = ($data['is_nhis'] ?? '0') === '1';
        if (! $data['is_nhis']) {
            $data['nhis_number'] = null;
        }
        // Imported records represent existing patients, so importing them must not create a new registration bill.
        $data['registration_paid_at'] = $data['registration_paid_at'] ?: now();
        Patient::create($data);

        return 'added';
    }

    private function importDepartment(array $row): string
    {
        $data = $this->validate($row, ['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);
        if (Department::where('name', $data['name'])->exists()) {
            return 'skipped';
        }
        Department::create($data);

        return 'added';
    }

    private function importStaff(array $row, User $actor): string
    {
        $data = $this->validate($row, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::staff()))],
            'department_name' => ['nullable', 'string', 'max:120'],
        ]);
        $department = filled($data['department_name'] ?? null)
            ? Department::where('name', $data['department_name'])->firstOrFail()
            : null;

        if (User::where('email', $data['email'])->exists() || StaffInvitation::where('email', $data['email'])->whereNull('accepted_at')->exists()) {
            return 'skipped';
        }

        $this->staffInvitations->issue($data['email'], Role::from($data['role']), $department?->id, $actor);

        return 'added';
    }

    private function importConsultationPrice(array $row): string
    {
        $data = $this->validate($row, [
            'department_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);
        $department = Department::where('name', $data['department_name'])->firstOrFail();
        if (ConsultationType::where('department_id', $department->id)->where('name', $data['name'])->exists()) {
            return 'skipped';
        }
        ConsultationType::create([
            'department_id' => $department->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'is_active' => ($data['is_active'] ?? '1') === '1',
        ]);

        return 'added';
    }

    private function importLabTest(array $row, User $actor): string
    {
        $data = $this->validate($row, [
            'department_name' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:50'],
            'sample_type' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);
        $departmentName = trim((string) ($data['department_name'] ?? '')) ?: 'Laboratory';
        $data['department_id'] = $actor->hasRole(Role::Lab)
            ? $actor->department_id
            : Department::where('name', $departmentName)->value('id');
        if (! $data['department_id']) {
            return 'skipped';
        }
        $data['code'] = filled($data['code'] ?? null) ? strtoupper($data['code']) : null;
        if (LabTest::where('department_id', $data['department_id'])->where(fn ($query) => $query->where('name', $data['name'])
            ->orWhere(fn ($query) => filled($data['code']) ? $query->where('code', $data['code']) : $query->whereRaw('1 = 0')))->exists()) {
            return 'skipped';
        }
        $data['is_active'] = ($data['is_active'] ?? '1') === '1';
        LabTest::create($data);

        return 'added';
    }

    private function importDrug(array $row): string
    {
        $data = $this->validate($row, [
            'name' => ['required', 'string', 'max:150'],
            'generic_name' => ['nullable', 'string', 'max:150'],
            'form' => ['nullable', 'string', 'max:30'],
            'strength' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);
        if (Drug::where('name', $data['name'])->where('strength', $data['strength'] ?? null)->where('form', $data['form'] ?? null)->exists()) {
            return 'skipped';
        }
        $data['is_active'] = ($data['is_active'] ?? '1') === '1';
        $drug = $this->drugs->create($data);
        if (! $data['is_active']) {
            $drug->update(['is_active' => false]);
        }

        return 'added';
    }

    private function validate(array $row, array $rules): array
    {
        return Validator::make($row, $rules)->validate();
    }

    private function records(string $dataset, ?User $actor = null): iterable
    {
        return match ($dataset) {
            'patients' => Patient::orderBy('id')->cursor()->map(fn (Patient $patient) => [
                'hospital_number' => $patient->hospital_number, 'first_name' => $patient->first_name, 'last_name' => $patient->last_name,
                'gender' => $patient->gender, 'date_of_birth' => $patient->date_of_birth?->toDateString(), 'phone' => $patient->phone,
                'email' => $patient->email, 'address' => $patient->address, 'blood_group' => $patient->blood_group, 'genotype' => $patient->genotype,
                'allergies' => $patient->allergies, 'next_of_kin_name' => $patient->next_of_kin_name, 'next_of_kin_phone' => $patient->next_of_kin_phone,
                'is_nhis' => (int) $patient->is_nhis, 'nhis_number' => $patient->nhis_number, 'registration_paid_at' => $patient->registration_paid_at?->toDateString(),
            ]),
            'departments' => Department::orderBy('name')->cursor()->map(fn (Department $department) => ['name' => $department->name, 'description' => $department->description]),
            'staff' => User::with('department')->where('role', '!=', Role::Patient->value)->orderBy('id')->cursor()->map(fn (User $user) => [
                'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value, 'department_name' => $user->department?->name,
            ]),
            'consultation-prices' => ConsultationType::with('department')->orderBy('department_id')->orderBy('name')->cursor()->map(fn (ConsultationType $type) => [
                'department_name' => $type->department?->name, 'name' => $type->name, 'description' => $type->description,
                'price' => $type->price, 'is_active' => (int) $type->is_active,
            ]),
            'lab-catalog' => LabTest::query()->when($actor?->hasRole(Role::Lab), fn ($query) => $query->where('department_id', $actor->department_id ?? 0))
                ->orderBy('id')->cursor()->map(fn (LabTest $test) => [
                'department_name' => $test->department?->name, 'name' => $test->name, 'code' => $test->code, 'category' => $test->category, 'sample_type' => $test->sample_type,
                'price' => $test->price, 'turnaround_hours' => $test->turnaround_hours, 'description' => $test->description, 'is_active' => (int) $test->is_active,
            ]),
            'pharmacy-catalog' => Drug::orderBy('id')->cursor()->map(fn (Drug $drug) => [
                'name' => $drug->name, 'generic_name' => $drug->generic_name, 'form' => $drug->form, 'strength' => $drug->strength,
                'unit_price' => $drug->unit_price, 'stock_quantity' => $drug->stock_quantity, 'reorder_level' => $drug->reorder_level,
                'is_active' => (int) $drug->is_active,
            ]),
        };
    }

    private function friendlyError(\Throwable $exception): string
    {
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            return collect($exception->errors())->flatten()->implode(' ');
        }

        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 'The department does not exist. Import departments first and match the department name exactly.';
        }

        return 'The row could not be imported. Check required values and duplicate records.';
    }
}
