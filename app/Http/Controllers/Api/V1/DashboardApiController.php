<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\VisitResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardApiController
{
    public function __invoke(Request $request, DashboardService $dashboard): array
    {
        $data = $dashboard->data($request->user());

        return ['cards' => $data['cards'], 'queue' => VisitResource::collection($data['queue'])];
    }
}
