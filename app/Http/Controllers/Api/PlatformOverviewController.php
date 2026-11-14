<?php

namespace App\Http\Controllers\Api;

use App\Services\PlatformOverviewService;
use Illuminate\Http\JsonResponse;

class PlatformOverviewController extends ApiController
{
    public function __construct(
        protected PlatformOverviewService $platformOverview,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->platformOverview->overview(),
        ]);
    }
}
