<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Services\Contracts\PayoutServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class PayoutSettlementTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    public function test_customer_paid_processing_fee_is_retained_from_the_payout(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);

        $this->paidOrder($tenant->id, $event->id, [
            'buyer_processing_fee' => 5000,
            'processing_fee_bearer' => 'customer',
            'total' => 105000,
        ]);

        $payout = app(PayoutServiceInterface::class)->settle($tenant->id, $event->id);

        $this->assertSame('105000.00', $payout->gross_amount);
        $this->assertSame('5000.00', $payout->commission_amount);
        $this->assertSame('100000.00', $payout->net_amount);
        $this->assertSame(5000.0, (float) $payout->metadata['processing_fees']);
    }

    public function test_absorbed_processing_fee_is_deducted_from_the_organiser_payout(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant, ['processing_fee_bearer' => 'organizer']);

        $this->paidOrder($tenant->id, $event->id, [
            'buyer_processing_fee' => 0,
            'processing_fee_bearer' => 'organizer',
            'total' => 100000,
        ]);

        $payout = app(PayoutServiceInterface::class)->settle($tenant->id, $event->id);

        $this->assertSame('100000.00', $payout->gross_amount);
        $this->assertSame('5000.00', $payout->commission_amount);
        $this->assertSame('95000.00', $payout->net_amount);
        $this->assertSame(5000.0, (float) $payout->metadata['processing_fees']);
    }

    private function paidOrder(int $tenantId, int $eventId, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'reference' => 'CSE-TEST-'.uniqid(),
            'buyer_name' => 'Amina Nakato',
            'buyer_email' => 'amina@example.com',
            'status' => 'paid',
            'subtotal' => 100000,
            'processing_fee' => 5000,
            'buyer_processing_fee' => 5000,
            'processing_fee_bearer' => 'customer',
            'gateway_fee_percent' => 3.5,
            'custospark_fee_percent' => 1.5,
            'gateway_fee' => 3500,
            'custospark_fee' => 1500,
            'discount' => 0,
            'total' => 105000,
            'currency' => 'UGX',
            'paid_at' => now(),
        ], $overrides));
    }
}
