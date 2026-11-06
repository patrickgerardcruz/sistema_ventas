<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('username')->unique();
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role', 20)->default('seller');
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        foreach (['categories', 'brands'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->timestamps();
            });
        }
        foreach (['customers', 'suppliers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('document', 20)->nullable()->unique();
                $t->string('email')->nullable();
                $t->string('phone', 30)->nullable();
                $t->string('address')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 60)->unique();
            $t->string('barcode', 80)->nullable()->unique();
            $t->string('name');
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('cost_cents');
            $t->unsignedBigInteger('price_cents');
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('min_stock')->default(5);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('cash_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->unsignedBigInteger('opening_cents');
            $t->bigInteger('expected_cents')->nullable();
            $t->unsignedBigInteger('counted_cents')->nullable();
            $t->bigInteger('difference_cents')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_key')->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('cash_session_id')->constrained();
            $t->string('payment', 20);
            $t->unsignedBigInteger('subtotal_cents');
            $t->unsignedBigInteger('discount_cents')->default(0);
            $t->unsignedBigInteger('total_cents');
            $t->unsignedBigInteger('cost_cents');
            $t->unsignedBigInteger('received_cents');
            $t->string('status', 20)->default('completed');
            $t->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->string('product_name');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('price_cents');
            $t->unsignedBigInteger('cost_cents');
        });
        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_key')->unique();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->string('reference', 80)->nullable();
            $t->unsignedBigInteger('total_cents');
            $t->timestamps();
        });
        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('cost_cents');
        });
        Schema::create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cash_session_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('type', 20);
            $t->unsignedBigInteger('amount_cents');
            $t->string('description');
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('type', 20);
            $t->integer('quantity');
            $t->unsignedInteger('balance');
            $t->string('reference');
            $t->timestamps();
        });
        Schema::create('sale_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->unique()->constrained();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('cash_session_id')->constrained();
            $t->string('reason');
            $t->unsignedBigInteger('total_cents');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->text('detail');
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'sale_returns', 'stock_movements', 'cash_movements', 'purchase_items', 'purchases', 'sale_items', 'sales', 'cash_sessions', 'products', 'suppliers', 'customers', 'brands', 'categories', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
