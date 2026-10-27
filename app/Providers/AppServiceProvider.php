<?php

namespace App\Providers;

use App\Models\HospitalSetting;
use App\Models\Hospital;
use App\Support\HospitalContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(HospitalContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerHospitalScopes();

        RedirectIfAuthenticated::redirectUsing(fn (Request $request) => $request->user()?->isGlobalAdmin()
            ? route('global-admin.dashboard')
            : route('dashboard'));

        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Runs lazily when these views render, so it is safe during migrations
        // View::composer(['layouts.app', 'billing.receipt'], function ($view) {
        //     $view->with('hospital', HospitalSetting::current());
        // });
        View::composer(['layouts.app', 'layouts.navigation', 'billing.receipt', 'lab.report'], function ($view) {
            $hospital = HospitalSetting::current();
            $view->with('hospital', $hospital);
            $view->with('hospitalModules', $hospital->hospital?->modules()->where('enabled', true)->pluck('module')->all() ?? []);
        });
    }

    private function registerHospitalScopes(): void
    {
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || $class === Hospital::class) {
                continue;
            }

            $model = new $class;

            $class::addGlobalScope('hospital', function (Builder $query) use ($class, $model): void {
                $context = app(HospitalContext::class);
                if (app()->runningInConsole() && $context->id() === null) {
                    return;
                }

                // User lookup must remain available before a tenant is resolved during login.
                if ($class === \App\Models\User::class) {
                    $hospitalId = app(HospitalContext::class)->id();
                    if ($hospitalId !== null) {
                        $query->where($model->qualifyColumn('hospital_id'), $hospitalId);
                    }

                    return;
                }

                // Invitation links carry a high-entropy token and resolve their hospital after lookup.
                if (in_array($class, [\App\Models\StaffInvitation::class, \App\Models\PatientInvitation::class], true)
                    && app(HospitalContext::class)->id() === null) {
                    return;
                }

                $hospitalId = $context->id();
                if ($hospitalId === null) {
                    $request = request();
                    $user = $request->is('api/*') ? $request->user('sanctum') : $request->user();
                    $hospitalId = $user?->hospital_id;

                    if ($hospitalId) {
                        $context->set((int) $hospitalId);
                    }
                }

                if ($hospitalId === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where($model->qualifyColumn('hospital_id'), $hospitalId);
            });

            $class::creating(function (Model $record): void {
                $context = app(HospitalContext::class);
                if (app()->runningInConsole() && $context->id() === null) {
                    return;
                }

                if ($record instanceof \App\Models\User && $record->is_global_admin && $context->id() === null) {
                    $record->setAttribute('hospital_id', null);

                    return;
                }

                $hospitalId = $context->id();
                abort_unless($hospitalId, 403, 'A hospital context is required to create this record.');
                $record->setAttribute('hospital_id', $hospitalId);
            });
        }
    }
}
