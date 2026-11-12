<?php

namespace Tests\Unit\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PesaPalGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PesaPalGatewayTest extends TestCase
{
    private const BASE = 'https://pesapal.test/v3';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'pesapal.environment' => 'sandbox',
            'pesapal.base_url_sandbox' => self::BASE,
            'pesapal.base_url_production' => self::BASE,
            'pesapal.consumer_key' => 'sandbox-key',
            'pesapal.consumer_secret' => 'sandbox-secret',
            'pesapal.ipn_id' => 'ipn-123',
            'pesapal.bypass' => false,
            'pesapal.enabled' => true,
            'pesapal.callback_url' => 'http://localhost:8000/api/v1/payments/webhook',
        ]);
    }

    private function order(): Order
    {
        $order = new Order([
            'tenant_id' => 1,
            'event_id' => 2,
            'reference' => 'CSE-261111-ABCD1234',
            'buyer_name' => 'Amina Nakato',
            'buyer_email' => 'amina@students.cocis.ac.ug',
            'buyer_phone' => '0771234567',
            'currency' => 'UGX',
            'total' => 102000,
            'status' => 'pending',
        ]);

        $order->id = 55;

        return $order;
    }

    private function payment(): Payment
    {
        $payment = new Payment([
            'tenant_id' => 1,
            'order_id' => 55,
            'provider' => 'pesapal',
            'amount' => 102000,
            'currency' => 'UGX',
            'status' => 'pending',
        ]);

        $payment->id = 7;

        return $payment;
    }

    public function test_it_submits_pesapal_order_and_returns_checkout_details(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => 'access-token'], 200),
            self::BASE.'/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-001',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapalv3/payment/track-001',
            ], 200),
        ]);

        $result = (new PesaPalGateway)->initialize($this->order(), $this->payment());

        $this->assertSame('track-001', $result['provider_reference']);
        $this->assertSame('https://cybqa.pesapal.com/pesapalv3/payment/track-001', $result['checkout_url']);

        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/api/Transactions/SubmitOrderRequest')
                && $request['id'] === 'CSE-261111-ABCD1234'
                && (float) $request['amount'] === 102000.0
                && $request['notification_id'] === 'ipn-123'
                && $request['billing_address']['email_address'] === 'amina@students.cocis.ac.ug';
        });
    }

    public function test_it_verifies_a_successful_pesapal_transaction(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => 'access-token'], 200),
            self::BASE.'/api/Transactions/GetTransactionStatus*' => Http::response([
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'amount' => 102000,
                'currency' => 'UGX',
            ], 200),
        ]);

        $result = (new PesaPalGateway)->verify('track-001');

        $this->assertSame('successful', $result['status']);
        $this->assertSame(1, $result['payload']['status_code']);
    }

    public function test_it_reports_failed_pesapal_transactions(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => 'access-token'], 200),
            self::BASE.'/api/Transactions/GetTransactionStatus*' => Http::response([
                'status_code' => 2,
                'payment_status_description' => 'Failed',
            ], 200),
        ]);

        $this->assertSame('failed', (new PesaPalGateway)->verify('track-002')['status']);
    }

    public function test_bypass_mode_approves_payments_without_calling_pesapal(): void
    {
        Http::fake();

        config(['pesapal.bypass' => true]);

        $gateway = new PesaPalGateway;
        $result = $gateway->initialize($this->order(), $this->payment());

        $this->assertSame('BYPASS-7', $result['provider_reference']);
        $this->assertStringEndsWith('/orders/CSE-261111-ABCD1234', $result['checkout_url']);
        $this->assertSame('successful', $gateway->verify('BYPASS-7')['status']);

        Http::assertNothingSent();
    }

    public function test_bypass_mode_is_ignored_outside_local_environments(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => 'access-token'], 200),
            self::BASE.'/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-003',
                'redirect_url' => 'https://pay.pesapal.com/v3/payment/track-003',
            ], 200),
        ]);

        $this->app['env'] = 'production';
        config(['pesapal.bypass' => true]);

        $result = (new PesaPalGateway)->initialize($this->order(), $this->payment());

        $this->assertSame('track-003', $result['provider_reference']);
        $this->assertSame('https://pay.pesapal.com/v3/payment/track-003', $result['checkout_url']);
        $this->assertArrayNotHasKey('mode', $result['payload']);

        Http::assertSentCount(2);
    }

    public function test_it_throws_when_pesapal_credentials_are_rejected(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response([
                'error' => ['message' => 'Invalid credentials'],
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PesaPal authentication failed. Check the gateway credentials.');

        (new PesaPalGateway)->initialize($this->order(), $this->payment());
    }

    public function test_it_throws_when_pesapal_rejects_the_order(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => 'access-token'], 200),
            self::BASE.'/api/Transactions/SubmitOrderRequest' => Http::response([
                'error' => ['message' => 'Invalid amount'],
            ], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PesaPal could not start this payment.');

        (new PesaPalGateway)->initialize($this->order(), $this->payment());
    }
}
