<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStandalonePharmacySaleRequest;
use App\Models\HospitalSetting;
use App\Models\PharmacySale;
use App\Services\StandalonePharmacySaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StandalonePharmacySaleController extends Controller
{
    public function index(Request $request, StandalonePharmacySaleService $sales): View
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return view('standalone-pharmacy.sales.index', $sales->dashboardData($filters));
    }

    public function create(StandalonePharmacySaleService $sales): View
    {
        return view('standalone-pharmacy.sales.create', ['products' => $sales->productsForSale()]);
    }

    public function store(StoreStandalonePharmacySaleRequest $request, StandalonePharmacySaleService $sales): RedirectResponse
    {
        $sale = $sales->createSale($request->validated(), $request->user()->id);

        return redirect()->route('standalone-pharmacy.sales.show', $sale)
            ->with('success', "Sale {$sale->sale_number} completed successfully.");
    }

    public function show(PharmacySale $sale): View
    {
        return view('standalone-pharmacy.sales.show', [
            'sale' => $sale->load(['items', 'seller']),
            'hospital' => HospitalSetting::current(),
        ]);
    }
}
