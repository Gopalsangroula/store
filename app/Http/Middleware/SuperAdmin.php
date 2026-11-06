<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user) {
            return redirect()->guest(route('super.login'));
        }

        if (! $user->is_super_admin || $user->status !== 'active') {
            abort(403);
        }

        return $next($request);
    }
}
