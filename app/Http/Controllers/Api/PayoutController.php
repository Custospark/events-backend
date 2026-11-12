<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PayoutRequest;
use App\Http\Resources\PayoutCollection;
use App\Http\Resources\PayoutResource;
use App\Services\Contracts\PayoutServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends ApiController
{
    public function __construct(
        protected PayoutServiceInterface $payoutService,
    ) {}

    public function index(Request $request): PayoutCollection
    {
        return new PayoutCollection(
            $this->payoutService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'event_id']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $payout): PayoutResource
    {
        return new PayoutResource(
            $this->payoutService->getById($this->tenantId($request), $payout),
        );
    }

    public function settle(PayoutRequest $request): JsonResponse
    {
        $payout = $this->payoutService->settle(
            $this->tenantId($request),
            (int) $request->validated()['event_id'],
        );

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }
}
