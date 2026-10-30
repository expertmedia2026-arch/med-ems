<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use App\Support\HospitalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user's active hospital before tenant models run.
 *
 * Platform administrators intentionally bypass hospital workspaces, while
 * ordinary users must belong to an active hospital. This middleware therefore
 * establishes the context used by the global model scopes and create hooks.
 */
class SetHospitalContext
{
    public function __construct(private readonly HospitalContext $context) {}

    /** Establish tenant context, or reject the request before domain queries execute. */
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
