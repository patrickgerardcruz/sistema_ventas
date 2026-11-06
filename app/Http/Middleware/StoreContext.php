<?php

namespace App\Http\Middleware;

use App\Models\StoreUser;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class StoreContext
{
    public function handle(Request $request, Closure $next)
    {
        $store = Tenant::find($request->session()->get('store_id', ''));
        if (! $store || ! $request->session()->has('store_user_id')) {
            return redirect()->route('login');
        }
        if ($store->status !== 'active' || $store->provisioning_status !== 'ready' || ($store->subscription_until && now()->toDateString() > $store->subscription_until)) {
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['login' => 'El negocio está suspendido o su suscripción ha vencido. Contacta al administrador.']);
        }
        tenancy()->initialize($store);
        try {
            $user = StoreUser::find($request->session()->get('store_user_id'));
            if (! $user || ! $user->active) {
                $request->session()->invalidate();

                return redirect()->route('login');
            }
            $request->attributes->set('storeUser', $user);
            $request->attributes->set('store', $store);
            View::share(['store' => $store, 'storeUser' => $user]);

            return $next($request);
        } finally {
            tenancy()->end();
        }
    }
}
