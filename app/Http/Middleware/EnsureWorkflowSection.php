<?php

namespace App\Http\Middleware;

use App\Models\HospitalWorkflow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkflowSection
{
    public function handle(Request $request, Closure $next, string $section): Response
    {
        abort_unless(HospitalWorkflow::sectionEnabled('admission', $section), 404);

        return $next($request);
    }
}
