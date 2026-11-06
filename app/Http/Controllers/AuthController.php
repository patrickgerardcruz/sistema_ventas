<?php

namespace App\Http\Controllers;

use App\Models\StoreUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login', ['platform' => false]);
    }

    public function platformLogin()
    {
        return view('auth.login', ['platform' => true]);
    }

    public function authenticate(Request $r)
    {
        $data = $r->validate(['code' => 'required|string|max:20', 'identity' => 'required|string|max:255', 'password' => 'required|string|max:255']);
        $store = Tenant::where('code', strtoupper(trim($data['code'])))->where('status', 'active')->where('provisioning_status', 'ready')->first();
        $user = null;
        if ($store && (! $store->subscription_until || $store->subscription_until >= now()->toDateString())) {
            tenancy()->initialize($store);
            try {
                $user = StoreUser::where(fn ($q) => $q->where('email', $data['identity'])->orWhere('username', $data['identity']))->where('active', true)->first();
                if (! $user || ! Hash::check($data['password'], $user->password)) {
                    $user = null;
                }
            } finally {
                tenancy()->end();
            }
        }
        if (! $user) {
            throw ValidationException::withMessages(['login' => 'Código o credenciales incorrectos, o negocio inactivo.']);
        }
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        $r->session()->put(['store_id' => $store->id, 'store_user_id' => $user->id]);

        return redirect()->route('dashboard');
    }

    public function authenticatePlatform(Request $r)
    {
        $data = $r->validate(['identity' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['identity'])->where('is_platform_admin', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Credenciales incorrectas.']);
        }
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        $r->session()->put('platform_user_id', $user->id);

        return redirect()->route('platform.index');
    }

    public function logout(Request $r)
    {
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
