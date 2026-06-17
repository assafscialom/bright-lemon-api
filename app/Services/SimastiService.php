<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the Simasti SMS API (https://my.simasti.com).
 *
 * Auth is a JWT obtained from POST /api/v1/auth/login; we cache it and re-login
 * once on an auth error. SMS is sent via POST /api/v1/message-send. This is the
 * reusable piece intended to replace Twilio across the apps.
 */
class SimastiService
{
    private string $baseUrl;
    private string $login;
    private string $password;
    private ?string $sender;
    private int $expireHours;
    private int $timeout;

    public function __construct()
    {
        $cfg = config('services.simasti', []);
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://my.simasti.com'), '/');
        $this->login = (string) ($cfg['login'] ?? '');
        $this->password = (string) ($cfg['password'] ?? '');
        $this->sender = $cfg['sender'] ?? null;
        $this->expireHours = max(1, min(8760, (int) ($cfg['expire_hours'] ?? 24)));
        $this->timeout = (int) ($cfg['timeout'] ?? 30);
    }

    /** Whether Simasti is turned on and has credentials (else callers fall back to demo). */
    public function isConfigured(): bool
    {
        return (bool) config('services.simasti.enabled')
            && $this->login !== ''
            && $this->password !== '';
    }

    /**
     * A valid bearer token, cached until shortly before it expires.
     */
    public function token(bool $forceRefresh = false): ?string
    {
        if ($forceRefresh) {
            Cache::forget('simasti_token');
        }

        return Cache::remember('simasti_token', now()->addHours(max(1, $this->expireHours - 1)), function () {
            $response = Http::timeout($this->timeout)->acceptJson()->asJson()->post(
                $this->baseUrl.'/api/v1/auth/login',
                [
                    'login' => $this->login,
                    'password' => $this->password,
                    'expireInHours' => $this->expireHours, // integer (Simasti rejects a string)
                ]
            );

            $token = $response->json('token');
            if (! $response->successful() || ! $token) {
                Log::error('Simasti login failed', ['status' => $response->status(), 'body' => $response->json()]);

                return null;
            }

            return $token;
        });
    }

    /**
     * Send a single SMS. $to must be E.164 (e.g. +9725...). Returns true on success.
     */
    public function sendSms(string $to, string $message, ?string $from = null): bool
    {
        $from = $from ?: $this->sender;

        $send = fn (?string $token) => Http::timeout($this->timeout)->acceptJson()->asJson()
            ->withToken($token)
            ->post($this->baseUrl.'/api/v1/message-send', [
                'message' => $message,
                'from' => $from,
                'to' => [$to],
                'isOTP' => true,
                'dlrState' => 'all',
            ]);

        $response = $send($this->token());

        // Re-login once if the cached token was rejected (Simasti error code 4 = auth).
        if ($response->status() === 401 || $this->isAuthError($response->json())) {
            $response = $send($this->token(true));
        }

        $ok = $response->successful() && (int) ($response->json('responseCode') ?? -1) === 0;
        if (! $ok) {
            Log::error('Simasti sendSms failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        return $ok;
    }

    private function isAuthError(mixed $body): bool
    {
        if (! is_array($body) || ($body['statusMessage'] ?? null) !== 'Error') {
            return false;
        }

        foreach (($body['data'] ?? []) as $error) {
            if ((int) ($error['code'] ?? 0) === 4) {
                return true;
            }
        }

        return false;
    }
}
