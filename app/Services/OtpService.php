<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Generates, stores and verifies a 6-digit phone OTP, delivering it over SMS via
 * Simasti. The code lives in the (database) cache keyed by phone for 10 minutes.
 */
class OtpService
{
    private const TTL_MINUTES = 10;

    public function __construct(private SimastiService $simasti) {}

    /**
     * Generate a 6-digit code, store it, and SMS it. Returns true if the SMS was sent.
     */
    public function send(string $phone): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $appName = config('app.name', 'Bright Lemon');
        $sent = $this->simasti->sendSms($phone, "{$appName} verification code: {$code}");

        if ($sent) {
            Cache::put($this->key($phone), $code, now()->addMinutes(self::TTL_MINUTES));
        }

        return $sent;
    }

    /**
     * Verify a submitted code against the stored one. Consumes it on success.
     */
    public function verify(string $phone, string $code): bool
    {
        $stored = Cache::get($this->key($phone));

        if ($stored !== null && hash_equals((string) $stored, $code)) {
            Cache::forget($this->key($phone));

            return true;
        }

        return false;
    }

    private function key(string $phone): string
    {
        return 'bl_otp:'.preg_replace('/\D+/', '', $phone);
    }
}
