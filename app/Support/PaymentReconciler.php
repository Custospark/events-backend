<?php

namespace App\Support;

final class PaymentReconciler
{
    /**
     * @return array{
     *     status: string,
     *     code: string,
     *     gateway_amount: ?float,
     *     gateway_currency: ?string,
     *     gateway_fee: ?float,
     *     expected_amount: float,
     *     expected_gateway_fee: float,
     *     fee_variance: ?float,
     *     fee_bearer_code: string,
     *     fee_bearer: string,
     *     organiser_payout: float,
     *     custospark_revenue: float
     * }
     */
    public static function reconcile(array $order, array $payload, string $gatewayStatus): array
    {
        $expectedAmount = (float) ($order['total'] ?? 0);
        $expectedGatewayFee = (float) ($order['gateway_fee'] ?? 0);
        $custosparkFee = (float) ($order['custospark_fee'] ?? 0);
        $feeBearer = EventFeeCalculator::normalizeBearer($order['processing_fee_bearer'] ?? null);
        $status = self::status($gatewayStatus);

        $gatewayAmount = self::amount($payload);
        $gatewayCurrency = self::currency($payload);
        $gatewayFee = self::fee($payload);

        $feeVariance = $gatewayFee === null ? null : round($gatewayFee - $expectedGatewayFee, 2);
        $payoutBase = $gatewayAmount ?? $expectedAmount;
        $payoutFee = $gatewayFee ?? $expectedGatewayFee;
        $organiserPayout = round(max(0, $payoutBase - $payoutFee - $custosparkFee), 2);

        return [
            'status' => $status,
            'code' => self::code($order, $payload, $gatewayFee, $feeVariance, $status),
            'gateway_amount' => $gatewayAmount,
            'gateway_currency' => $gatewayCurrency,
            'gateway_fee' => $gatewayFee,
            'expected_amount' => round($expectedAmount, 2),
            'expected_gateway_fee' => round($expectedGatewayFee, 2),
            'fee_variance' => $feeVariance,
            'fee_bearer_code' => EventFeeCalculator::bearerCode($feeBearer),
            'fee_bearer' => $feeBearer,
            'organiser_payout' => $organiserPayout,
            'custospark_revenue' => round($custosparkFee, 2),
        ];
    }

    private static function status(string $gatewayStatus): string
    {
        $status = strtolower($gatewayStatus);

        return match (true) {
            in_array($status, ['success', 'succeeded', 'paid', 'completed', 'successful'], true) => 'succeeded',
            in_array($status, ['failed', 'cancelled', 'canceled', 'declined', 'expired'], true) => 'failed',
            default => 'pending',
        };
    }

    private static function code(
        array $order,
        array $payload,
        ?float $gatewayFee,
        ?float $feeVariance,
        string $status,
    ): string {
        if ($status !== 'succeeded') {
            return 'GATEWAY_'.$status;
        }

        if (self::bypass($payload)) {
            return 'BYPASSED';
        }

        $gatewayAmount = self::amount($payload);
        $currency = self::currency($payload);

        if ($gatewayAmount === null || $currency === null) {
            return 'MISSING_AMOUNT';
        }

        if (strtoupper($currency) !== strtoupper((string) ($order['currency'] ?? ''))) {
            return 'CURRENCY_MISMATCH';
        }

        if (abs($gatewayAmount - (float) ($order['total'] ?? 0)) > 0.01) {
            return 'AMOUNT_MISMATCH';
        }

        if ($gatewayFee === null) {
            return 'FEE_UNAVAILABLE';
        }

        if ($feeVariance !== null && abs($feeVariance) > 0.01) {
            return 'FEE_VARIANCE';
        }

        return 'RECONCILED';
    }

    private static function bypass(array $payload): bool
    {
        return ($payload['bypass'] ?? false) === true
            || ($payload['mode'] ?? null) === 'bypass'
            || str_starts_with((string) ($payload['order_tracking_id'] ?? ''), 'BYPASS-');
    }

    private static function amount(array $payload): ?float
    {
        $amount = data_get($payload, 'amount')
            ?? data_get($payload, 'data.amount')
            ?? data_get($payload, 'payment.amount');

        return $amount === null ? null : (float) $amount;
    }

    private static function currency(array $payload): ?string
    {
        $currency = data_get($payload, 'currency')
            ?? data_get($payload, 'data.currency')
            ?? data_get($payload, 'payment.currency');

        return $currency === null ? null : strtoupper((string) $currency);
    }

    private static function fee(array $payload): ?float
    {
        $fee = data_get($payload, 'fee')
            ?? data_get($payload, 'processing_fee')
            ?? data_get($payload, 'gateway_fee')
            ?? data_get($payload, 'data.fee')
            ?? data_get($payload, 'payment.fee');

        return $fee === null ? null : (float) $fee;
    }
}
