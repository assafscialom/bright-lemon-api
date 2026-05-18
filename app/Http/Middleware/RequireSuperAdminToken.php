<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdminToken
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
}
