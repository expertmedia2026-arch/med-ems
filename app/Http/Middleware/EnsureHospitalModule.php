<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use App\Support\HospitalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHospitalModule
{
    public function __construct(private readonly HospitalContext $context) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $hospitalId = $this->context->id();
        abort_unless($hospitalId && Hospital::query()->whereKey($hospitalId)->where('is_active', true)->exists(), 403);

        $hospital = Hospital::query()->findOrFail($hospitalId);
        abort_unless($hospital->hasModule($module), 404);

        return $next($request);
    }
}
