<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class TicketingFlowTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    private function fakeGateway(string $trackingId = 'TRACK-123'): void
    {
        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock) use ($trackingId): void {
            $mock->shouldReceive('initialize')->andReturn([
                'provider_reference' => $trackingId,
                'checkout_url' => 'https://cybqa.pesapal.com/pesapalv3/payment/'.$trackingId,
                'payload' => ['order_tracking_id' => $trackingId],
            ]);

            $mock->shouldReceive('verify')->with($trackingId)->andReturn([
                'status' => 'successful',
                'payload' => [
                    'status_code' => 1,
                    'order_tracking_id' => $trackingId,
                    'amount' => 102000,
                    'currency' => 'UGX',
                ],
            ]);

            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mock->shouldReceive('refund')->andReturn([
                'status' => 'manual',
                'payload' => ['message' => 'Refund manually.'],
            ]);
        });
    }

    private function buyTickets(string $tenantSlug, int $eventId, int $ticketTypeId, int $quantity = 2)
    {
        return $this->postJson("/api/v1/public/tenants/{$tenantSlug}/orders", [
            'event_id' => $eventId,
            'buyer_name' => 'Amina Nakato',
            'buyer_email' => 'amina@students.cocis.ac.ug',
            'buyer_phone' => '0771234567',
            'items' => [
                ['ticket_type_id' => $ticketTypeId, 'quantity' => $quantity],
            ],
        ]);
    }

    public function test_public_buyer_can_create_an_order_and_reserve_stock(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event, ['price' => 50000, 'quantity_total' => 100]);

        $this->fakeGateway();

        $response = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated();

        $reference = $response->json('data.reference');

        $this->assertStringStartsWith('CSE-', $reference);
        $this->assertSame('102000.00', $response->json('data.total'));
        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame(
            'https://cybqa.pesapal.com/pesapalv3/payment/TRACK-123',
            $response->json('data.metadata.checkout_url'),
        );

        $this->assertDatabaseHas('orders', [
            'reference' => $reference,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $response->json('data.id'),
            'provider' => 'pesapal',
            'provider_reference' => 'TRACK-123',
            'status' => 'pending',
        ]);

        $this->assertSame(2, $ticketType->fresh()->quantity_sold);
    }

    public function test_order_is_rejected_when_stock_is_exhausted(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event, ['quantity_total' => 1]);

        $this->fakeGateway();

        $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Not enough Student tickets remain.');

        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pesapal_webhook_marks_the_order_paid_and_issues_tickets(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->getJson('/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]))->assertOk();

        $order = Order::query()->where('reference', $reference)->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(2, $order->tickets()->count());
        $this->assertSame('succeeded', $order->payments()->first()->status);

        $ticket = $order->tickets()->first();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/tickets/check-in', ['code' => $ticket->code])
            ->assertOk()
            ->assertJsonPath('data.ticket.status', 'used');

        $this->postJson('/api/v1/tickets/check-in', ['code' => $ticket->code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This ticket is not valid for check-in.');
    }

    public function test_ticket_email_is_sent_once_with_each_ticket_qr(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $url = '/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]);

        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertOk();

        $transport = Mail::mailer()->getSymfonyTransport();

        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());

        $rawMessage = $transport->messages()->first()->getOriginalMessage()->toString();
        $order = Order::query()->where('reference', $reference)->firstOrFail();

        $this->assertStringContainsString('Your tickets are ready', $rawMessage);
        $this->assertStringContainsString($order->tickets()->first()->code, $rawMessage);
        $this->assertStringContainsString('View and download your tickets', $rawMessage);
    }

    public function test_repeated_pesapal_notifications_do_not_duplicate_tickets(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $url = '/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]);

        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertOk();

        $order = Order::query()->where('reference', $reference)->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertSame(2, $order->tickets()->count());
    }

    public function test_payment_amount_mismatch_is_held_for_review(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->mock(PaymentGatewayInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initialize')->andReturn([
                'provider_reference' => 'TRACK-123',
                'checkout_url' => 'https://cybqa.pesapal.com/pesapalv3/payment/TRACK-123',
                'payload' => [],
            ]);
            $mock->shouldReceive('verify')->andReturn([
                'status' => 'successful',
                'payload' => ['status_code' => 1, 'amount' => 1000, 'currency' => 'UGX'],
            ]);
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
        });

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->getJson('/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]))->assertOk();

        $order = Order::query()->where('reference', $reference)->firstOrFail();
        $payment = $order->payments()->firstOrFail();

        $this->assertSame('pending', $order->status);
        $this->assertSame('requires_review', $payment->status);
        $this->assertSame(0, $order->tickets()->count());
    }

    public function test_late_payment_recovers_stock_and_issues_tickets(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event, ['quantity_total' => 10]);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        Order::query()->where('reference', $reference)->update(['expires_at' => now()->subMinute()]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/orders/expire-pending')->assertOk();

        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->getJson('/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]))->assertOk();

        $order = Order::query()->where('reference', $reference)->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertTrue((bool) ($order->metadata['recovered_after_release'] ?? false));
        $this->assertSame(2, $order->tickets()->count());
        $this->assertSame(2, $ticketType->fresh()->quantity_sold);
    }

    public function test_late_payment_is_marked_for_refund_when_stock_is_gone(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event, ['quantity_total' => 10]);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        Order::query()->where('reference', $reference)->update(['expires_at' => now()->subMinute()]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/orders/expire-pending')->assertOk();

        $ticketType->update(['quantity_sold' => 10]);

        $this->getJson('/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]))->assertOk();

        $order = Order::query()->where('reference', $reference)->firstOrFail();

        $this->assertSame('expired', $order->status);
        $this->assertSame('refund_pending', $order->payments()->firstOrFail()->status);
        $this->assertSame(0, $order->tickets()->count());
    }

    public function test_public_order_status_is_available_by_reference(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->getJson('/api/v1/public/orders/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.event.tenant.slug', 'cocis');

        $this->getJson('/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]))->assertOk();

        $this->getJson('/api/v1/public/orders/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonCount(2, 'data.tickets');

        $this->getJson('/api/v1/public/orders/CSE-UNKNOWN')
            ->assertStatus(422);
    }

    public function test_public_order_can_self_verify_payment_status(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->postJson('/api/v1/public/orders/'.$reference.'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->getJson('/api/v1/public/orders/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonCount(2, 'data.tickets');
    }

    public function test_pesapal_callback_redirects_to_the_frontend_order_page(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->get('/api/v1/payments/callback?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]))->assertRedirectContains('/orders/'.$reference.'?payment=success');
    }

    public function test_payment_reconciliation_settles_a_missed_callback(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('paid', Order::query()->where('reference', $reference)->firstOrFail()->status);
    }

    public function test_ticket_qr_tokens_verify_check_in_and_are_publicly_verifiable(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event);

        $this->fakeGateway();

        $reference = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated()
            ->json('data.reference');

        $this->getJson('/api/v1/payments/webhook?'.http_build_query([
            'OrderTrackingId' => 'TRACK-123',
            'OrderMerchantReference' => $reference,
        ]))->assertOk();

        $ticket = Order::query()->where('reference', $reference)->firstOrFail()->tickets()->firstOrFail();
        $token = (string) $ticket->qr_payload;

        $this->get('/api/v1/public/tenants/'.$tenant->slug.'/qr')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);

        $this->getJson('/api/v1/public/tickets/'.$token)
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.ticket.holder_name', 'A***a N****o');

        $this->assertArrayNotHasKey('holder_email', $this->getJson('/api/v1/public/tickets/'.$token)->json('data.ticket'));

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/tickets/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $this->get('/api/v1/tickets/'.$ticket->id.'/qr')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);

        $this->postJson('/api/v1/tickets/check-in', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.ticket.status', 'used');

        $this->postJson('/api/v1/tickets/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'This ticket is not valid for check-in.');

        $this->getJson('/api/v1/public/tickets/'.$token)
            ->assertOk()
            ->assertJsonPath('data.valid', false);

        $this->getJson('/api/v1/public/tickets/'.$token.'tampered')
            ->assertNotFound();
    }

    public function test_sandbox_bypass_completes_payment_without_calling_pesapal(): void
    {
        config(['pesapal.bypass' => true]);

        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event, ['quantity_total' => 10]);

        $response = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 2)
            ->assertCreated();

        $orderId = $response->json('data.id');
        $reference = $response->json('data.reference');
        $payment = Payment::query()->where('order_id', $orderId)->firstOrFail();

        $this->assertStringStartsWith('BYPASS-', $payment->provider_reference);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/payments/{$payment->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertSame('paid', Order::query()->where('reference', $reference)->firstOrFail()->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $orderId)->count());
    }

    public function test_expiring_a_pending_order_releases_reserved_stock(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);
        $ticketType = $this->makeTicketType($event, ['quantity_total' => 10]);

        $this->fakeGateway();

        $orderId = $this->buyTickets($tenant->slug, $event->id, $ticketType->id, 3)
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(3, $ticketType->fresh()->quantity_sold);

        Order::query()->whereKey($orderId)->update(['expires_at' => now()->subMinute()]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/orders/expire-pending')
            ->assertOk()
            ->assertJsonPath('data.expired', 1);

        $this->assertSame('expired', Order::query()->find($orderId)->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame('pending', Payment::query()->where('order_id', $orderId)->first()->status);
    }
}
