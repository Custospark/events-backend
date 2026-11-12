<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Services\Contracts\DashboardServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(
        protected DashboardServiceInterface $dashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $summary = $this->dashboardService->summary($this->tenantId($request));

        $summary['recent_orders'] = OrderResource::collection($summary['recent_orders']);

        return response()->json(['data' => $summary]);
    }
}
