<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RecordPayoutPaymentRequest;
use App\Http\Resources\PayoutAttachmentResource;
use App\Http\Resources\PayoutCollection;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Models\PayoutAttachment;
use App\Services\Contracts\PayoutServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function store(RecordPayoutPaymentRequest $request): PayoutResource
    {
        return new PayoutResource(
            $this->payoutService->record(
                $this->tenantId($request),
                $request->validated(),
            ),
        );
    }

    public function outstanding(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->payoutService->getOutstanding($this->tenantId($request)),
        ]);
    }

    private function accessiblePayout(Request $request, int $payout): Payout
    {
        $user = $request->user();

        if ($user?->is_platform_admin) {
            return Payout::query()->findOrFail($payout);
        }

        return Payout::query()
            ->where('tenant_id', $this->tenantId($request))
            ->whereKey($payout)
            ->firstOrFail();
    }

    public function attachments(Request $request, int $payout)
    {
        $payout = $this->accessiblePayout($request, $payout);

        return PayoutAttachmentResource::collection(
            PayoutAttachment::query()
                ->where('tenant_id', $payout->tenant_id)
                ->where('payout_id', $payout->id)
                ->latest()
                ->get(),
        );
    }

    public function downloadAttachment(Request $request, int $payout, int $attachment)
    {
        $payout = $this->accessiblePayout($request, $payout);

        $attachment = PayoutAttachment::query()
            ->where('tenant_id', $payout->tenant_id)
            ->where('payout_id', $payout->id)
            ->find($attachment);

        if ($attachment === null) {
            return response()->json(['message' => 'Evidence not found.'], 404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($attachment->path)) {
            return response()->json(['message' => 'Evidence file not found on storage.'], 404);
        }

        return response()->file($disk->path($attachment->path), [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
