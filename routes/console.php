<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create-superadmin {country_code} {mobile} {--name=Super Admin} {--email=}', function () {
    $phone = app(\App\Services\PhoneService::class)->normalize(
        (string) $this->argument('country_code'),
        (string) $this->argument('mobile'),
    );

    $email = $this->option('email')
        ?: 'superadmin+'.preg_replace('/\D+/', '', $phone).'@shiphome.local';

    $user = \App\Models\User::query()->updateOrCreate(
        ['phone' => $phone],
        [
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => Str::random(48),
            'role' => \App\Models\User::ROLE_SUPERADMIN,
            'email_verified_at' => now(),
        ],
    );

    $this->info("Superadmin ready: {$user->name} ({$user->phone})");
})->purpose('Create or update a superadmin user that can log in with phone OTP');
