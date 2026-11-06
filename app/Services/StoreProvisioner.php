<?php

namespace App\Services;

use App\Models\StoreUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

class StoreProvisioner
{
    public function create(array $data): Tenant
    {
        $tenant = null;
        try {
            // Persist the failed state first: MySQL CREATE DATABASE cannot be rolled back.
            $tenant = new Tenant([
                'id' => Str::uuid()->toString(), 'code' => strtoupper($data['code']), 'name' => $data['business_name'],
                'status' => 'active', 'plan' => $data['plan'] ?? 'Básico',
                'subscription_until' => $data['subscription_until'] ?? now()->addMonth()->toDateString(), 'provisioning_status' => 'pending',
            ]);
            $tenant->saveQuietly();
            event(new TenantCreated($tenant));
            $tenant->run(function () use ($data) {
                DB::table('business_settings')->insert([
                    'business_name' => $data['business_name'], 'owner_name' => $data['owner_name'], 'document_number' => $data['document_number'],
                    'phone' => $data['phone'] ?? null, 'email' => $data['email'], 'address' => $data['address'] ?? null,
                    'currency' => 'PEN', 'created_at' => now(), 'updated_at' => now(),
                ]);
                StoreUser::create(['name' => $data['owner_name'], 'username' => $data['username'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'admin']);
            });
            $tenant->update(['provisioning_status' => 'ready']);

            return $tenant;
        } catch (\Throwable $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            // Keep failed records for diagnosis; never drop a database from a catch block.
            if ($tenant?->exists) {
                $tenant->update(['provisioning_status' => 'failed', 'status' => 'suspended']);
            }
            throw $e;
        }
    }
}
