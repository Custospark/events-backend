<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasPermission($permission)) {
            return response()->json([
                'message' => 'You are not allowed to perform this action.',
                'errors' => ['authorization' => ["Missing permission: {$permission}."]],
            ], 403);
        }

        return $next($request);
    }
}
