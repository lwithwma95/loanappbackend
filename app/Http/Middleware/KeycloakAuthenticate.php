<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;

class KeycloakAuthenticate
{
    public function __construct(private KeycloakService $keycloak) {}

    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = $this->keycloak->verifyAccessToken(substr($header, 7));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::where('keycloak_id', $payload->sub)->first();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->status === 'inactive') {
            return response()->json(['message' => 'Your account has been deactivated. Contact an administrator.'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
