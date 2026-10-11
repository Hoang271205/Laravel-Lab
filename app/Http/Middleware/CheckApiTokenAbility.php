<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApiTokenAbility
{
    public function handle(Request $request, Closure $next)
    {
        $ability = match ($request->method()) {
            'GET', 'HEAD' => 'read', 'POST' => 'create', 'PUT', 'PATCH' => 'update', 'DELETE' => 'delete', default => null,
        };
        abort_unless($ability && $request->user()->tokenCan($ability), 403);

        return $next($request);
    }
}
