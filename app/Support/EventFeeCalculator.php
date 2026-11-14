<?php

namespace App\Support;

final class EventFeeCalculator
{
    public const BEARER_CUSTOMER = 'customer';

    public const BEARER_ORGANIZER = 'organizer';

    /**
     * @return array{gateway_percent: float, custospark_percent: float, processing_percent: float}
     */
    public static function percentages(): array
    {
        $gateway = max(0, (float) config('events.fees.gateway_percent', 3.5));
        $custospark = max(0, (float) config('events.fees.custospark_percent', 1.5));

        return [
            'gateway_percent' => $gateway,
            'custospark_percent' => $custospark,
            'processing_percent' => round($gateway + $custospark, 2),
        ];
    }

    public static function defaultBearer(): string
    {
        return self::normalizeBearer(config('events.fees.default_bearer'));
    }

    public static function normalizeBearer(?string $bearer): string
    {
        return $bearer === self::BEARER_ORGANIZER
            ? self::BEARER_ORGANIZER
            : self::BEARER_CUSTOMER;
    }

    public static function bearerCode(?string $bearer): string
    {
        return 'PFB:'.strtoupper(self::normalizeBearer($bearer));
    }

    /**
     * @return array{
     *     processing_fee_bearer: string,
     *     gateway_percent: float,
     *     custospark_percent: float,
     *     processing_percent: float,
     *     gateway_fee: float,
     *     custospark_fee: float,
     *     processing_fee: float,
     *     buyer_processing_fee: float
     * }
     */
    public static function breakdown(float $subtotal, ?string $feeBearer = null): array
    {
        $percentages = self::percentages();
        $bearer = $feeBearer === null
            ? self::defaultBearer()
            : self::normalizeBearer($feeBearer);

        $gatewayFee = round($subtotal * ($percentages['gateway_percent'] / 100), 2);
        $custosparkFee = round($subtotal * ($percentages['custospark_percent'] / 100), 2);
        $processingFee = round($gatewayFee + $custosparkFee, 2);

        return [
            'processing_fee_bearer' => $bearer,
            'gateway_percent' => $percentages['gateway_percent'],
            'custospark_percent' => $percentages['custospark_percent'],
            'processing_percent' => $percentages['processing_percent'],
            'gateway_fee' => $gatewayFee,
            'custospark_fee' => $custosparkFee,
            'processing_fee' => $processingFee,
            'buyer_processing_fee' => $bearer === self::BEARER_ORGANIZER ? 0.0 : $processingFee,
        ];
    }
}
