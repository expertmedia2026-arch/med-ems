<?php

use App\Models\Hospital;
use App\Services\DrugInventoryService;
use App\Services\MortuaryCaseService;
use App\Support\HospitalContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pharmacy:expire-stock-reservations', function (DrugInventoryService $inventory) {
    // Console commands have no authenticated user, so each tenant must be selected
    // explicitly before its globally-scoped stock records can be processed.
    $context = app(HospitalContext::class);
    $count = 0;
    foreach (Hospital::where('is_active', true)->get() as $hospital) {
        $context->set((int) $hospital->id);
        $count += $inventory->expireReservations();
    }
    $context->set(null);
    $this->info("Expired {$count} unpaid stock reservation(s).");
})->purpose('Release expired unpaid drug stock reservations');

Schedule::command('pharmacy:expire-stock-reservations')->everyMinute()->withoutOverlapping();

Artisan::command('mortuary:accrue-storage', function (MortuaryCaseService $mortuary) {
    // Reuse one context instance but replace its hospital on every iteration;
    // otherwise charges could be queried or written against the previous tenant.
    $context = app(HospitalContext::class);
    $count = 0;
    foreach (Hospital::where('is_active', true)->get() as $hospital) {
        $context->set((int) $hospital->id);
        $count += $mortuary->accrueStorageCharges();
    }
    $context->set(null);
    $this->info("Updated storage charges for {$count} mortuary case(s).");
})->purpose('Add elapsed mortuary storage charges to invoices');

Schedule::command('mortuary:accrue-storage')->dailyAt('00:10')->withoutOverlapping();
