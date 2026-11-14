<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\DeclinePayoutRequest;
use App\Http\Requests\InitiatePayoutRequest;
use App\Http\Requests\MarkPayoutPaidRequest;
use App\Http\Resources\PayoutCollection;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\PayoutWorkflowService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PayoutWorkflowController extends ApiController
{
    public function __construct(
        protected PayoutWorkflowService $workflow,
    ) {}

    public function tenantSummary(Request $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        if ($tenant === null) {
            return response()->json(['message' => 'Tenant could not be resolved.'], 404);
        }

        $summary = $this->workflow->summary($tenant);

        $requests = Payout::query()
            ->with(['requestedBy', 'tenant'])
            ->where('tenant_id', $tenant->id)
            ->where('initiation', 'organizer_request')
            ->latest('requested_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                ...$summary,
                'currency' => $tenant->currency,
                'requests' => PayoutResource::collection($requests),
            ],
        ]);
    }

    public function tenantRequests(Request $request): PayoutCollection
    {
        return new PayoutCollection(
            Payout::query()
                ->with(['requestedBy', 'tenant', 'attachments'])
                ->where('tenant_id', $this->tenantId($request))
                ->where('initiation', 'organizer_request')
                ->latest('requested_at')
                ->paginate($this->perPage($request)),
        );
    }

    public function initiate(InitiatePayoutRequest $request): PayoutResource
    {
        return new PayoutResource(
            $this->workflow->initiate($this->tenantId($request), $request->validated()),
        );
    }

    public function overview(): JsonResponse
    {
        $overview = $this->workflow->platformOverview();

        return response()->json([
            'data' => [
                'summary' => $overview['summary'],
                'organisers' => $overview['organisers'],
                'requests' => PayoutResource::collection($overview['requests']),
                'completed_requests' => PayoutResource::collection($overview['completed_requests']),
            ],
        ]);
    }

    public function startProcessing(int $payout): PayoutResource|JsonResponse
    {
        $record = Payout::query()->find($payout);

        if ($record === null) {
            return response()->json(['message' => 'Payout request not found.'], 404);
        }

        try {
            return new PayoutResource($this->workflow->markProcessing($record));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function markPaid(MarkPayoutPaidRequest $request, int $payout): PayoutResource|JsonResponse
    {
        $record = Payout::query()->find($payout);

        if ($record === null) {
            return response()->json(['message' => 'Payout request not found.'], 404);
        }

        try {
            return new PayoutResource($this->workflow->markPaid($record, $request->validated(), $request->file('evidence')));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function decline(DeclinePayoutRequest $request, int $payout): PayoutResource|JsonResponse
    {
        $record = Payout::query()->find($payout);

        if ($record === null) {
            return response()->json(['message' => 'Payout request not found.'], 404);
        }

        try {
            return new PayoutResource($this->workflow->decline($record, $request->validated()['reason']));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
