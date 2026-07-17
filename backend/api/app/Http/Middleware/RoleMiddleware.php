<?php

namespace App\Http\Middleware;

use App\Support\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role || ! in_array($user->role->code, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.forbidden'),
                'error_code' => ApiErrorCode::Forbidden,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
