<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to the handful of admin-editable global values.
 *
 * Cached, because the VIP fee is read on every quote and it changes perhaps a
 * few times a year. The cache is dropped on write rather than given a TTL: a
 * price the admin has just changed must take effect on the next quote, not
 * whenever a timer happens to expire.
 */
class Settings
{
    public const VIP_COLLECTION_FEE = 'vip_collection_fee';

    private const CACHE_PREFIX = 'app_setting:';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::CACHE_PREFIX . $key,
            fn () => AppSetting::query()->where('key', $key)->value('value')
        ) ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        AppSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * The VIP collection fee, in ILS including VAT.
     *
     * Falls back to 0 rather than to 130 when the setting is missing: a
     * hardcoded fallback price would keep charging customers after someone
     * deliberately cleared the row, and a fee of zero is visible in the
     * numbers while a phantom 130 is not.
     */
    public static function vipCollectionFee(): float
    {
        return round((float) self::get(self::VIP_COLLECTION_FEE, 0), 2);
    }
}
