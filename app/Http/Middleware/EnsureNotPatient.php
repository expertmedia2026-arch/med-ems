<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotPatient
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === Role::Patient) {
            return redirect()->route('portal.home');
        }

        return $next($request);
    }
}