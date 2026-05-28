<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryGroupCountry extends Model
{
    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CountryGroup::class, 'country_group_id');
    }

    /**
     * Resolve a destination as typed on the form (either an ISO-2 code or the
     * full country name) to its canonical registry code. Returns null when the
     * destination isn't in any group (e.g. an unpriced country).
     */
    public static function resolveCode(?string $nameOrCode): ?string
    {
        $value = trim((string) $nameOrCode);
        if ($value === '') {
            return null;
        }

        if (strlen($value) === 2) {
            $byCode = static::whereRaw('UPPER(country_code) = ?', [strtoupper($value)])->first();
            if ($byCode) {
                return $byCode->country_code;
            }
        }

        $byName = static::whereRaw('LOWER(country_name) = ?', [strtolower($value)])->first();

        return $byName?->country_code;
    }
}
