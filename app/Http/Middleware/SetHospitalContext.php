<?php

namespace App\Http\Middleware;

use App\Support\HospitalContext;
use App\Models\Hospital;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetHospitalContext
{
    public function __construct(private readonly HospitalContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->is('api/*') ? $request->user('sanctum') : $request->user();
        if ($user?->isGlobalAdmin()) {
            if ($request->is('api/*')) {
                abort(403, 'Platform administrators use the global hospital console.');
            }

            return redirect()->route('global-admin.dashboard');
        }

        $hospitalId = $user?->hospital_id;
        abort_unless($hospitalId, 403, 'Your account is not connected to a hospital.');

        abort_unless(Hospital::query()->whereKey($hospitalId)->where('is_active', true)->exists(), 403, 'This hospital account is inactive.');

        $this->context->set((int) $hospitalId);

        return $next($request);
    }
}
