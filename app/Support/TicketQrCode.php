<?php

namespace App\Support;

final class TicketQrCode
{
    public static function make(string $code, int $eventId, int $tenantId): string
    {
        $payload = self::base64UrlEncode(json_encode([
            'v' => 1,
            'c' => $code,
            'e' => $eventId,
            't' => $tenantId,
            'i' => time(),
        ], JSON_THROW_ON_ERROR));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', $payload, self::secret(), true),
        );

        return $payload.'.'.$signature;
    }

    /**
     * @return array{v: int, c: string, e: int, t: int, i: int}|null
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        $expected = self::base64UrlEncode(
            hash_hmac('sha256', $payload, self::secret(), true),
        );

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = base64_decode(self::base64UrlDecode($payload), true);

        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        if (! is_array($data)
            || ($data['v'] ?? null) !== 1
            || ! is_string($data['c'] ?? null)
            || ! is_int($data['e'] ?? null)
            || ! is_int($data['t'] ?? null)
        ) {
            return null;
        }

        return $data;
    }

    private static function secret(): string
    {
        return (string) config('app.key');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        return strtr($value, '-_', '+/');
    }
}
