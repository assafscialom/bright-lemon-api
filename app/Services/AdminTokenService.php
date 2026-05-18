<?php

namespace App\Services;

use App\Models\User;

class AdminTokenService
{
    public function issue(User $user): string
    {
        $payload = [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'role' => $user->role,
            'context' => 'admin',
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addHours(12)->timestamp,
        ];

        $payload['signature'] = $this->signature($payload);

        return base64_encode(json_encode($payload));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validate(?string $token): ?array
    {
        if (! $token) {
            return null;
        }

        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload) || ! isset($payload['signature'], $payload['expires_at'])) {
            return null;
        }

        if ((int) $payload['expires_at'] < now()->timestamp) {
            return null;
        }

        $expected = $this->signature($payload);

        if (! hash_equals($expected, (string) $payload['signature'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signature(array $payload): string
    {
        unset($payload['signature']);
        ksort($payload);

        return hash_hmac('sha256', json_encode($payload), (string) config('app.key'));
    }
}
