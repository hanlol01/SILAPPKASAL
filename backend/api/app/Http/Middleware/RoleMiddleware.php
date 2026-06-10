<?php

namespace App\Http\Middleware;

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
                'message' => 'You do not have permission to perform this action',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
