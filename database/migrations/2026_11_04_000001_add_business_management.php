<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->string('code', 20)->nullable()->unique();
            $t->string('name')->nullable();
            $t->string('status', 20)->default('active');
            $t->string('plan', 40)->default('Básico');
            $t->date('subscription_until')->nullable();
            $t->string('provisioning_status', 20)->default('ready');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['code', 'name', 'status', 'plan', 'subscription_until', 'provisioning_status']));
    }
};
