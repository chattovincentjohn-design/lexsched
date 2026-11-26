<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage in routes: ->middleware('role:secretary,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless($user && $user->is_active && $user->hasRole(...$allowed), 403, 'You do not have access to this page.');

        return $next($request);
    }
}
