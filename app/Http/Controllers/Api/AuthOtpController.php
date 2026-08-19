<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingDropLocation;
use App\Models\User;
use App\Services\AdminTokenService;
use App\Services\OtpService;
use App\Services\PhoneService;
use App\Services\SimastiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthOtpController extends Controller
{
    public function send(Request $request, PhoneService $phones, OtpService $otp, SimastiService $simasti): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'max:8'],
            'mobile' => ['required', 'string', 'max:30'],
            'context' => ['required', Rule::in(['tracking', 'admin'])],
        ]);

        $phone = $phones->normalize($data['country_code'], $data['mobile']);

        if (
            $data['context'] === 'admin'
            && ! $this->findSuperAdminByPhone($phone, $data['country_code'], $data['mobile'])
            && ! $this->findAdminDropLocationByPhone($phone, $data['mobile'])
        ) {
            return response()->json([
                'message' => 'This phone number is not allowed to access admin.',
            ], 403);
        }

        // Real SMS via Simasti when configured; otherwise the demo code path.
        if ($simasti->isConfigured()) {
            if (! $otp->send($phone)) {
                return response()->json([
                    'message' => 'Could not send the verification code. Please try again.',
                ], 502);
            }

            return response()->json([
                'message' => 'Verification code sent.',
                'phone' => $phone,
            ]);
        }

        return response()->json([
            'message' => 'Verification code sent.',
            'phone' => $phone,
            'demo' => true,
            'demo_code' => config('brightlemon.demo_otp'),
        ]);
    }

    public function verify(Request $request, PhoneService $phones, AdminTokenService $tokens, OtpService $otp, SimastiService $simasti): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'max:8'],
            'mobile' => ['required', 'string', 'max:30'],
            'context' => ['required', Rule::in(['tracking', 'admin'])],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $phone = $phones->normalize($data['country_code'], $data['mobile']);
        $adminUser = null;
        $adminDropLocation = null;

        if ($data['context'] === 'admin') {
            $adminUser = $this->findSuperAdminByPhone($phone, $data['country_code'], $data['mobile']);
            $adminDropLocation = $adminUser
                ? null
                : $this->findAdminDropLocationByPhone($phone, $data['mobile']);

            if (! $adminUser && ! $adminDropLocation) {
                return response()->json([
                    'message' => 'This phone number is not allowed to access admin.',
                ], 403);
            }
        }

        $codeValid = $simasti->isConfigured()
            ? $otp->verify($phone, $data['code'])
            : hash_equals(config('brightlemon.demo_otp'), $data['code']);

        if (! $codeValid) {
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
            'token' => $adminUser
                ? $tokens->issue($adminUser)
                : ($adminDropLocation
                    ? $tokens->issueForDropLocation($adminDropLocation, $phone)
                    : base64_encode(json_encode($tokenPayload))),
            'user' => $adminUser ? [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'role' => $adminUser->role,
            ] : ($adminDropLocation ? [
                'id' => null,
                'name' => $adminDropLocation->name,
                'role' => User::ROLE_ADMIN,
                'drop_location_id' => $adminDropLocation->id,
                // Branch identity surfaced on the splash sidebar / printed
                // labels so a branch admin sees their own branch info instead
                // of the hard-coded "Manhattan Hub" placeholder.
                'branch_code' => $adminDropLocation->code,
                'branch_address' => trim(
                    ($adminDropLocation->address_line_1 ?? '')
                    .($adminDropLocation->city ? ', '.$adminDropLocation->city : '')
                ),
            ] : null),
            'demo' => ! $simasti->isConfigured(),
        ]);
    }

    /**
     * The superadmin behind a phone number, however it happens to be stored.
     *
     * This was an exact match on the one normalized string, which is why a
     * superadmin saved as +972544522993 could not log in while branch users
     * could: the branch lookup below already tolerated the trunk zero and this
     * one did not. Matching every spelling costs one IN clause and removes a
     * whole class of "the number looks right but nothing happens".
     */
    private function findSuperAdminByPhone(string $phone, ?string $countryCode = null, ?string $mobile = null): ?User
    {
        $candidates = $countryCode !== null && $mobile !== null
            ? (new PhoneService())->variants($countryCode, $mobile)
            : [$phone];

        if (! in_array($phone, $candidates, true)) {
            $candidates[] = $phone;
        }

        return User::query()
            ->whereIn('phone', $candidates)
            ->where('role', User::ROLE_SUPERADMIN)
            ->first();
    }

    private function findAdminDropLocationByPhone(string $normalizedPhone, string $mobile): ?ShippingDropLocation
    {
        $normalizedDigits = preg_replace('/\D+/', '', $normalizedPhone) ?? '';
        $mobileDigits = preg_replace('/\D+/', '', $mobile) ?? '';

        return ShippingDropLocation::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->get()
            ->first(function (ShippingDropLocation $location) use ($normalizedDigits, $mobileDigits) {
                $locationDigits = preg_replace('/\D+/', '', (string) $location->phone) ?? '';
                $mobileWithoutLeadingZero = ltrim($mobileDigits, '0');

                return $locationDigits !== ''
                    && (
                        $locationDigits === $normalizedDigits
                        || $locationDigits === $mobileDigits
                        || ($mobileWithoutLeadingZero !== '' && str_ends_with($locationDigits, $mobileWithoutLeadingZero))
                    );
            });
    }
}
