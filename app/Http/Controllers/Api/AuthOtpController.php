<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminTokenService;
use App\Services\PhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthOtpController extends Controller
{
    public function send(Request $request, PhoneService $phones): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'max:8'],
            'mobile' => ['required', 'string', 'max:30'],
            'context' => ['required', Rule::in(['tracking', 'admin'])],
        ]);

        $phone = $phones->normalize($data['country_code'], $data['mobile']);

        if ($data['context'] === 'admin' && ! $this->findSuperAdminByPhone($phone)) {
            return response()->json([
                'message' => 'This phone number is not allowed to access admin.',
            ], 403);
        }

        return response()->json([
            'message' => 'Verification code sent.',
            'phone' => $phone,
            'demo' => true,
            'demo_code' => config('brightlemon.demo_otp'),
        ]);
    }

    public function verify(Request $request, PhoneService $phones, AdminTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'max:8'],
            'mobile' => ['required', 'string', 'max:30'],
            'context' => ['required', Rule::in(['tracking', 'admin'])],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $phone = $phones->normalize($data['country_code'], $data['mobile']);
        $adminUser = null;

        if ($data['context'] === 'admin') {
            $adminUser = $this->findSuperAdminByPhone($phone);

            if (! $adminUser) {
                return response()->json([
                    'message' => 'This phone number is not allowed to access admin.',
                ], 403);
            }
        }

        if (! hash_equals(config('brightlemon.demo_otp'), $data['code'])) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $tokenPayload = [
            'phone' => $phone,
            'context' => $data['context'],
            'issued_at' => now()->timestamp,
            'signature' => Hash::make($phone.'|'.$data['context'].'|'.now()->toDateString()),
        ];

        return response()->json([
            'message' => 'Verified successfully.',
            'phone' => $phone,
            'context' => $data['context'],
            'token' => $adminUser ? $tokens->issue($adminUser) : base64_encode(json_encode($tokenPayload)),
            'user' => $adminUser ? [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'role' => $adminUser->role,
            ] : null,
            'demo' => true,
        ]);
    }

    private function findSuperAdminByPhone(string $phone): ?User
    {
        return User::query()
            ->where('phone', $phone)
            ->where('role', User::ROLE_SUPERADMIN)
            ->first();
    }
}
