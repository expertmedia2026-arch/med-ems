<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pharmacy:expire-stock-reservations', function (\App\Services\DrugInventoryService $inventory) {
    $count = $inventory->expireReservations();
    $this->info("Expired {$count} unpaid stock reservation(s).");
})->purpose('Release expired unpaid drug stock reservations');

Schedule::command('pharmacy:expire-stock-reservations')->everyMinute()->withoutOverlapping();

Artisan::command('mortuary:accrue-storage', function (\App\Services\MortuaryCaseService $mortuary) {
    $count = $mortuary->accrueStorageCharges();
    $this->info("Updated storage charges for {$count} mortuary case(s).");
})->purpose('Add elapsed mortuary storage charges to invoices');

Schedule::command('mortuary:accrue-storage')->dailyAt('00:10')->withoutOverlapping();
