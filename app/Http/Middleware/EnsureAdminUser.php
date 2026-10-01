<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('admin.login'));
        }

        $user = Auth::user();
        if (!$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized admin access.'], 403);
            }
            abort(403, 'Unauthorized. Administrator access required.');
        }

        return $next($request);
    }
}
