<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            // 404 so non-platform users cannot even confirm the panel exists.
            return response()->json(['message' => 'Not found.', 'code' => 'not_found'], 404);
        }

        return $next($request);
    }
}
