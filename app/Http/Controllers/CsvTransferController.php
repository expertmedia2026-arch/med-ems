<?php

namespace App\Http\Controllers;

use App\Services\CsvTransferService;
use Illuminate\Http\Request;

class CsvTransferController extends Controller
{
    private const ACCESS = [
        'patients' => ['admin', 'receptionist'],
        'departments' => ['admin'],
        'staff' => ['admin'],
        'consultation-prices' => ['admin'],
        'lab-catalog' => ['admin', 'lab'],
        'pharmacy-catalog' => ['admin', 'pharmacist'],
    ];

    public function export(Request $request, string $dataset, CsvTransferService $transfer)
    {
        $this->authorizeDataset($request, $dataset);

        if ($dataset === 'lab-catalog' && $request->user()->hasRole('lab')) {
            abort_if(! $request->user()->department_id, 403, 'Assign this staff member to a department first.');
        }

        return $transfer->export($dataset, $request->boolean('template'), $request->user());
    }

    public function import(Request $request, string $dataset, CsvTransferService $transfer)
    {
        $this->authorizeDataset($request, $dataset);
        if ($dataset === 'lab-catalog' && $request->user()->hasRole('lab')) {
            abort_if(! $request->user()->department_id, 403, 'Assign this staff member to a department first.');
        }
        $request->validate(['file' => ['required', 'file', 'extensions:csv', 'max:10240']]);

        $result = $transfer->import($dataset, $request->file('file'), $request->user());

        return back()->with('csv_transfer_result', ['dataset' => $dataset] + $result);
    }

    private function authorizeDataset(Request $request, string $dataset): void
    {
        abort_unless(isset(self::ACCESS[$dataset]), 404);
        abort_unless($request->user()->hasRole(...self::ACCESS[$dataset]), 403);
    }
}
