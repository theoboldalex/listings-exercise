<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Take-home auth stub.
 *
 * A real deployment would authenticate the request (Sanctum, session, etc.).
 * To keep this exercise focused on the feature rather than on auth plumbing,
 * every API request is resolved as the seeded demo user. Build user-scoped
 * work against $request->user() / auth()->user() exactly as you normally would.
 */
class ActAsDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Set on the auth guard, not only the request, so auth()->user(), Gate
        // and policies see the demo user too; $request->user() reads the guard.
        //
        // There is no user until the database is seeded (`php artisan migrate --seed`),
        // which is the same shape as a genuinely unauthenticated request.
        $demoUser = User::query()->orderBy('id')->first();

        if ($demoUser !== null) {
            Auth::setUser($demoUser);
        }

        return $next($request);
    }
}
