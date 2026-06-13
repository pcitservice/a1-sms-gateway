<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            throw new HttpException(403, 'Admin only.');
        }
        return $next($request);
    }
}
