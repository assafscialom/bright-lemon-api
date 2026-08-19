<?php

namespace App\Services;

class PhoneService
{
    /**
     * Combine a country code and a locally-typed mobile into E.164.
     *
     * The part people actually type is the national number, and in Israel —
     * as in India, Thailand and most of the world — that is habitually
     * written with a leading 0. That 0 is a trunk prefix: it means "I am
     * dialling inside this country" and is dropped the moment a country code
     * is put in front of it. Concatenating the two verbatim produced
     * +9720544522993, which matches nothing and is not a real number, and is
     * why a superadmin stored as +972544522993 could not log in.
     *
     * Also tolerates a mobile that already carries the country code, since the
     * same field is filled by hand in the admin and by pickers in the form.
     */
    public function normalize(string $countryCode, string $mobile): string
    {
        $country = preg_replace('/\D+/', '', $countryCode) ?? '';
        $number = preg_replace('/\D+/', '', $mobile) ?? '';

        // "+972 972544522993" and "+972 544522993" are the same number.
        if ($country !== '' && str_starts_with($number, $country) && strlen($number) > strlen($country)) {
            $number = substr($number, strlen($country));
        }

        // Drop the national trunk prefix.
        $number = ltrim($number, '0');

        return '+' . $country . $number;
    }

    /**
     * Every spelling of a number that might be sitting in the database.
     *
     * Rows written before normalize() dropped the trunk prefix still hold the
     * old shape, and a lookup that only accepts the corrected form would lock
     * those accounts out — trading one login bug for another. Used by the
     * admin phone lookup so both generations of data resolve.
     *
     * @return list<string>
     */
    public function variants(string $countryCode, string $mobile): array
    {
        $country = preg_replace('/\D+/', '', $countryCode) ?? '';
        $raw = preg_replace('/\D+/', '', $mobile) ?? '';
        $national = ltrim(
            str_starts_with($raw, $country) && strlen($raw) > strlen($country)
                ? substr($raw, strlen($country))
                : $raw,
            '0'
        );

        return array_values(array_unique(array_filter([
            '+' . $country . $national,          // the correct form
            '+' . $country . '0' . $national,    // what the old code produced
            '0' . $national,                     // stored as a local number
            $national,
        ])));
    }
}
