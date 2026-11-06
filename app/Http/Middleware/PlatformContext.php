<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;

class PlatformContext
{
    public function handle($request, Closure $next)
    {
        $user = User::find($request->session()->get('platform_user_id'));
        if (! $user || ! $user->is_platform_admin) {
            return redirect()->route('platform.login');
        }

        return $next($request);
    }
}
