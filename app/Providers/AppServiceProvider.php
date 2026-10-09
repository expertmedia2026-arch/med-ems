<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HospitalSetting;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Runs lazily when these views render, so it is safe during migrations
        // View::composer(['layouts.app', 'billing.receipt'], function ($view) {
        //     $view->with('hospital', HospitalSetting::current());
        // });
        View::composer(['layouts.app', 'billing.receipt', 'lab.report'], function ($view) {
            $view->with('hospital', HospitalSetting::current());
        });
    }
}
