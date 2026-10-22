<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMainPharmacyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $departmentName = trim((string) $user?->department?->name);

        abort_unless(
            ! $user?->hasRole(Role::Pharmacist)
                || preg_match('/accident\s*(?:&|and)\s*emergency|emergency department/i', $departmentName) !== 1,
            403,
            'A&E pharmacy staff use the A&E Pharmacy workspace. Main pharmacy access is restricted to main pharmacy staff.'
        );

        return $next($request);
    }
}
