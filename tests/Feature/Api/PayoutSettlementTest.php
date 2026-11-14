<?php

namespace Tests\Feature\Api;

use App\Mail\StandardEmail;
use App\Models\Order;
use App\Services\Contracts\PayoutServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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
            'processing_fee' => 5000,
            'gateway_fee' => 3500,
            'custospark_fee' => 1500,
            'total' => 105000,
        ]);

        $payout = app(PayoutServiceInterface::class)->record($tenant->id, [
            'event_id' => $event->id,
            'provider_reference' => 'BANK-OUT-1',
        ]);

        $this->assertSame('100000.00', $payout->gross_amount);
        $this->assertSame('0.00', $payout->commission_amount);
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
            'processing_fee' => 5000,
            'gateway_fee' => 3500,
            'custospark_fee' => 1500,
            'total' => 100000,
        ]);

        $payout = app(PayoutServiceInterface::class)->record($tenant->id, [
            'event_id' => $event->id,
            'provider_reference' => 'BANK-OUT-2',
        ]);

        $this->assertSame('95000.00', $payout->gross_amount);
        $this->assertSame('0.00', $payout->commission_amount);
        $this->assertSame('95000.00', $payout->net_amount);
        $this->assertSame(5000.0, (float) $payout->metadata['processing_fees']);
    }

    public function test_organiser_can_initiate_a_payout_request(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant(['slug' => 'cocis', 'name' => 'COCIS']);
        $event = $this->makeEvent($tenant);
        $admin = $this->makeAdmin($tenant);

        $this->paidOrder($tenant->id, $event->id, [
            'buyer_processing_fee' => 2500,
            'processing_fee_bearer' => 'customer',
            'total' => 52500,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 40000,
            'priority' => 'high',
            'note' => 'Need the first disbursement.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'initiated')
            ->assertJsonPath('data.net_amount', '40000.00')
            ->assertJsonPath('data.request_priority', 'high')
            ->assertJsonPath('data.initiation', 'organizer_request');

        Mail::assertSent(StandardEmail::class);

        $this->getJson('/api/v1/tenant/payouts/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'initiated');
    }

    public function test_organiser_cannot_request_more_than_the_available_balance(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $admin = $this->makeAdmin($tenant);

        $this->paidOrder($tenant->id, $event->id, [
            'total' => 52500,
            'buyer_processing_fee' => 2500,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 50001,
            'priority' => 'normal',
        ])->assertStatus(422);

        $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 50000.01,
            'priority' => 'normal',
        ])->assertStatus(422);

        $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 50000,
            'priority' => 'normal',
        ])->assertCreated();
    }

    public function test_initiated_requests_reduce_the_available_balance(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $admin = $this->makeAdmin($tenant);

        $this->paidOrder($tenant->id, $event->id, [
            'total' => 50000,
            'buyer_processing_fee' => 2500,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 30000,
            'priority' => 'normal',
        ])->assertCreated();

        $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 30000,
            'priority' => 'normal',
        ])->assertStatus(422);
    }

    public function test_platform_can_process_and_record_a_payout(): void
    {
        Mail::fake();
        Storage::fake('local');

        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $admin = $this->makeAdmin($tenant);
        $platformAdmin = $this->makeAdmin($tenant, [
            'tenant_id' => null,
            'role_id' => null,
            'is_platform_admin' => true,
        ]);

        $this->paidOrder($tenant->id, $event->id, [
            'total' => 52500,
            'buyer_processing_fee' => 2500,
        ]);

        Sanctum::actingAs($admin);
        $requestPayout = $this->postJson('/api/v1/tenant/payouts/requests', [
            'amount' => 50000,
            'priority' => 'high',
            'note' => 'Bank counter payout.',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($platformAdmin);

        $this->getJson('/api/v1/platform/payouts')
            ->assertOk()
            ->assertJsonPath('data.summary.available', 0)
            ->assertJsonPath('data.organisers.0.available', 0)
            ->assertJsonPath('data.requests.0.id', $requestPayout['id'])
            ->assertJsonPath('data.requests.0.request_priority', 'high');

        $this->postJson("/api/v1/platform/payouts/{$requestPayout['id']}/start-processing")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $file = UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf');

        $this->post(
            "/api/v1/platform/payouts/{$requestPayout['id']}/mark-paid",
            [
                'provider_reference' => 'BANK-COUNTER-9999',
                'paid_at' => now()->toDateTimeString(),
                'notes' => 'Paid to the organiser via bank counter.',
                'evidence' => $file,
            ],
        )->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.provider_reference', 'BANK-COUNTER-9999')
            ->assertJsonCount(1, 'data.attachments');

        $this->assertDatabaseCount('payouts', 1);
        $this->getJson('/api/v1/platform/payouts')
            ->assertOk()
            ->assertJsonPath('data.summary.paid_total', 50000);
    }

    private function paidOrder(int $tenantId, int $eventId, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'user_id' => null,
            'reference' => 'CSE-TEST-'.uniqid(),
            'buyer_name' => 'Amina Nakato',
            'buyer_email' => 'amina@example.com',
            'status' => 'paid',
            'subtotal' => 50000,
            'processing_fee' => 2500,
            'buyer_processing_fee' => 2500,
            'processing_fee_bearer' => 'customer',
            'gateway_fee_percent' => 3.5,
            'custospark_fee_percent' => 1.5,
            'gateway_fee' => 1750,
            'custospark_fee' => 750,
            'discount' => 0,
            'total' => 52500,
            'currency' => 'UGX',
            'paid_at' => now(),
        ], $overrides));
    }
}
