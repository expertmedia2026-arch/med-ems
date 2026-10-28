<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\User;
use App\Support\HospitalContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Hospital10StaffSeeder extends Seeder
{
    private const HOSPITAL_ID = 10;

    private const PASSWORD = 'password';

    public function run(): void
    {
        Hospital::query()->findOrFail(self::HOSPITAL_ID);

        $context = app(HospitalContext::class);
        $previousHospitalId = $context->id();
        $context->set(self::HOSPITAL_ID);

        try {
            DB::transaction(function (): void {
                foreach ($this->staffRows() as $row) {
                    $this->seedStaffMember($row);
                }
            });
        } finally {
            $context->set($previousHospitalId);
        }

        $this->command?->info('Seeded the staff CSV accounts for hospital ID '.self::HOSPITAL_ID.'.');
    }

    /** @return list<array{name: string, email: string, role: string, department_name: ?string}> */
    private function staffRows(): array
    {
        $path = resource_path('csv-templates/staff.csv');
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open staff CSV at {$path}.");
        }

        try {
            $headers = fgetcsv($handle);
            if (! is_array($headers)) {
                throw new RuntimeException('The staff CSV is empty.');
            }

            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            $required = ['name', 'email', 'role', 'department_name'];
            if (array_diff($required, $headers)) {
                throw new RuntimeException('The staff CSV is missing one or more required columns.');
            }

            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $record = array_combine($headers, array_pad($values, count($headers), ''));
                $rows[] = [
                    'name' => trim((string) ($record['name'] ?? '')),
                    'email' => strtolower(trim((string) ($record['email'] ?? ''))),
                    'role' => strtolower(trim((string) ($record['role'] ?? ''))),
                    'department_name' => filled($record['department_name'] ?? null)
                        ? trim((string) $record['department_name'])
                        : null,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @param array{name: string, email: string, role: string, department_name: ?string} $row */
    private function seedStaffMember(array $row): void
    {
        $role = Role::tryFrom($row['role']);
        if (! $role || $role === Role::Patient) {
            throw new RuntimeException("Invalid staff role '{$row['role']}' for {$row['email']}.");
        }

        $departmentName = $row['department_name'];
        if ($role->isFinanceStaff()) {
            $departmentName = Department::FINANCE_NAME;
        } elseif ($role->isPharmacyStaff()) {
            $departmentName = Department::PHARMACY_NAME;
        }

        $departmentId = $departmentName
            ? Department::query()->where('name', $departmentName)->value('id')
            : null;

        if ($departmentName && ! $departmentId && ! $role->isFinanceStaff() && ! $role->isPharmacyStaff()) {
            throw new RuntimeException("Department '{$departmentName}' is missing for {$row['email']} in hospital ID ".self::HOSPITAL_ID.'.');
        }

        $user = User::withoutGlobalScope('hospital')->where('email', $row['email'])->first();
        if ($user && (int) $user->hospital_id !== self::HOSPITAL_ID) {
            throw new RuntimeException("{$row['email']} already belongs to another hospital.");
        }

        $user ??= new User;
        $user->name = $row['name'];
        $user->email = $row['email'];
        $user->role = $role;
        $user->department_id = $departmentId;
        $user->hospital_id = self::HOSPITAL_ID;
        $user->is_global_admin = false;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->password = self::PASSWORD;
        $user->save();
    }
}
