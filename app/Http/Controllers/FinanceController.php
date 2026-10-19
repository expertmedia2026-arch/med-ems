<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceReportRequest;
use App\Models\HospitalSetting;
use App\Services\FinanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;

class FinanceController extends Controller
{
    public function index(FinanceReportRequest $request, FinanceReportService $finance)
    {
        return view('finance.index', $finance->indexData($request->validated()));
    }

    public function exportPdf(FinanceReportRequest $request, FinanceReportService $finance)
    {
        $data = $finance->indexData($request->validated(), forExport: true);
        $data['hospital'] = HospitalSetting::current();
        $logoPath = $data['hospital']->logo_path
            ? storage_path('app/public/'.$data['hospital']->logo_path)
            : null;
        $data['hospitalLogoPath'] = $logoPath && is_file($logoPath) ? $logoPath : null;
        $filename = 'financial-report-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('finance.report-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
