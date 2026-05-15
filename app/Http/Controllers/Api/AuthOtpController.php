<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'message' => 'Verification code sent.',
            'phone' => $phone,
            'demo' => true,
            'demo_code' => config('brightlemon.demo_otp'),
        ]);
    }

    public function verify(Request $request, PhoneService $phones): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'max:8'],
            'mobile' => ['required', 'string', 'max:30'],
            'context' => ['required', Rule::in(['tracking', 'admin'])],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! hash_equals(config('brightlemon.demo_otp'), $data['code'])) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $phone = $phones->normalize($data['country_code'], $data['mobile']);
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
            'token' => base64_encode(json_encode($tokenPayload)),
            'demo' => true,
        ]);
    }
}
