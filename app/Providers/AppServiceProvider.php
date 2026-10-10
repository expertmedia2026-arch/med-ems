<?php

namespace App\Providers;

use App\Models\HospitalSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Runs lazily when these views render, so it is safe during migrations
        // View::composer(['layouts.app', 'billing.receipt'], function ($view) {
        //     $view->with('hospital', HospitalSetting::current());
        // });
        View::composer(['layouts.app', 'billing.receipt', 'lab.report'], function ($view) {
            $view->with('hospital', HospitalSetting::current());
        });
    }
}
