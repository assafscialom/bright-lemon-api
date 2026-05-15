<?php

namespace App\Services;

class PhoneService
{
    public function normalize(string $countryCode, string $mobile): string
    {
        $country = preg_replace('/\D+/', '', $countryCode) ?? '';
        $number = preg_replace('/\D+/', '', $mobile) ?? '';

        return '+'.$country.$number;
    }
}
