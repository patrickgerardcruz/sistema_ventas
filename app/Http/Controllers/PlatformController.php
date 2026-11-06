<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\StoreProvisioner;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function index()
    {
        return view('platform.index', ['stores' => Tenant::orderByDesc('created_at')->get()]);
    }

    public function store(Request $r, StoreProvisioner $provisioner)
    {
        $data = $r->validate([
            'code' => ['required', 'regex:/^[A-Za-z0-9-]{3,20}$/', 'unique:tenants,code'],
            'business_name' => 'required|string|max:150', 'owner_name' => 'required|string|max:150', 'document_number' => 'required|digits:8',
            'email' => 'required|email|max:150', 'username' => ['required', 'regex:/^[A-Za-z0-9_.-]{3,40}$/'],
            'password' => 'required|string|min:10|max:128|confirmed', 'phone' => 'nullable|string|max:20', 'address' => 'nullable|string|max:200',
            'plan' => 'required|string|max:40', 'subscription_until' => 'required|date|after_or_equal:today',
        ]);
        $data['code'] = strtoupper($data['code']);
        if (Tenant::where('code', $data['code'])->exists()) {
            return back()->withErrors(['code' => 'Este código ya existe.'])->withInput($r->except(['password', 'password_confirmation']));
        }
        $store = $provisioner->create($data);

        return back()->with('success', "Negocio creado. Código de tienda: {$store->code}. Ya puede ingresar desde el acceso general.");
    }

    public function update(Request $r, string $id)
    {
        $data = $r->validate(['status' => 'required|in:active,suspended', 'plan' => 'required|string|max:40', 'subscription_until' => 'nullable|date']);
        Tenant::findOrFail($id)->update($data);

        return back()->with('success', 'Suscripción actualizada.');
    }
}
