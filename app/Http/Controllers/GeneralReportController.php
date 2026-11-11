<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralReportRequest;
use App\Models\HospitalSetting;
use App\Services\GeneralReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GeneralReportController extends Controller
{
    public function index(GeneralReportRequest $request, GeneralReportService $reports): View
    {
        return view('reports.general', $reports->indexData($request->validated()));
    }

    public function exportPdf(GeneralReportRequest $request, GeneralReportService $reports): Response
    {
        $data = $reports->indexData($request->validated());
        $data['hospital'] = HospitalSetting::current();
        $logoPath = $data['hospital']->logo_path
            ? storage_path('app/public/'.$data['hospital']->logo_path)
            : null;
        $data['hospitalLogoPath'] = $logoPath && is_file($logoPath) ? $logoPath : null;

        return Pdf::loadView('reports.general-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download('general-hospital-report-'.now()->format('Y-m-d').'.pdf');
    }
}
