<?php

namespace App\Http\Middleware;

use App\Models\ShippingDropLocation;
use App\Models\User;
use App\Services\AdminTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminToken
{
    public function __construct(private readonly AdminTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $payload = $this->tokens->validate($request->bearerToken());

        if (! $payload || ($payload['context'] ?? null) !== 'admin') {
            return response()->json(['message' => 'Admin authentication required.'], 401);
        }

        if (($payload['role'] ?? null) === User::ROLE_SUPERADMIN) {
            $user = User::query()
                ->whereKey($payload['user_id'] ?? null)
                ->where('phone', $payload['phone'] ?? null)
                ->where('role', User::ROLE_SUPERADMIN)
                ->first();

            if (! $user) {
                return response()->json(['message' => 'Admin access denied.'], 403);
            }

            $request->attributes->set('admin_user', $user);

            return $next($request);
        }

        if (($payload['role'] ?? null) === User::ROLE_ADMIN) {
            $location = ShippingDropLocation::query()
                ->whereKey($payload['drop_location_id'] ?? null)
                ->where('is_active', true)
                ->first();

            if (! $location) {
                return response()->json(['message' => 'Admin access denied.'], 403);
            }

            $request->attributes->set('admin_drop_location', $location);

            return $next($request);
        }

        return response()->json(['message' => 'Admin access denied.'], 403);
    }
}
