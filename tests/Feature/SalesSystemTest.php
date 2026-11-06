<?php

namespace Tests\Feature;

use App\Models\StoreUser;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger;
use App\Services\StoreProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesSystemTest extends TestCase
{
    use RefreshDatabase;

    private array $created = [];

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        foreach ($this->created as $tenant) {
            $tenant->delete();
        }
        parent::tearDown();
    }

    private function store(string $code = 'TESTA'): Tenant
    {
        $t = app(StoreProvisioner::class)->create(['code' => $code, 'business_name' => 'Negocio '.$code, 'owner_name' => 'Admin prueba', 'document_number' => '12345678', 'email' => 'admin@example.test', 'username' => 'admin', 'password' => 'Password1234']);
        $this->created[] = $t;

        return $t;
    }

    private function sessionFor(Tenant $t, ?int $uid = null): array
    {
        return ['store_id' => $t->id, 'store_user_id' => $uid ?? $t->run(fn () => StoreUser::first()->id)];
    }

    private function product(Tenant $t, int $stock = 10): int
    {
        return $t->run(fn () => DB::table('products')->insertGetId(['sku' => 'SKU-1', 'barcode' => '111', 'name' => 'Arroz', 'cost_cents' => 300, 'price_cents' => 500, 'stock' => $stock, 'min_stock' => 5, 'active' => true, 'created_at' => now(), 'updated_at' => now()]));
    }

    public function test_two_businesses_have_separate_databases_and_same_username(): void
    {
        $a = $this->store();
        $b = $this->store('TESTB');
        $this->assertNotSame($a->database()->getName(), $b->database()->getName());
        $this->product($a);
        $this->assertSame(1, $a->run(fn () => DB::table('products')->count()));
        $this->assertSame(0, $b->run(fn () => DB::table('products')->count()));
        $this->post('/login', ['code' => 'TESTB', 'identity' => 'admin', 'password' => 'Password1234'])->assertRedirect('/dashboard')->assertSessionHas('store_id', $b->id);
        $this->get('/catalogo/products')->assertOk()->assertDontSee('SKU-1');
        $this->get('/dashboard')->assertOk();
    }

    public function test_suspended_or_expired_business_cannot_access_existing_session(): void
    {
        $t = $this->store();
        $session = $this->sessionFor($t);
        $t->update(['status' => 'suspended']);
        $this->withSession($session)->get('/dashboard')->assertRedirect('/login');
        $t->update(['status' => 'active', 'subscription_until' => today()->subDay()->toDateString()]);
        $this->withSession($session)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_sale_is_atomic_rejects_overselling_and_duplicate_requests(): void
    {
        $t = $this->store();
        $pid = $this->product($t, 3);
        $session = $this->sessionFor($t);
        $this->withSession($session)->post('/caja/open', ['opening' => '100.00'])->assertRedirect();
        $body = ['request_key' => Str::uuid()->toString(), 'payment' => 'cash', 'discount' => '1.00', 'received' => '10.00', 'items' => [['product_id' => $pid, 'quantity' => 2]]];
        $this->post('/ventas', $body)->assertRedirect('/ventas/1/ticket');
        $this->post('/ventas', $body)->assertRedirect('/ventas/1/ticket');
        $t->run(function () {
            $this->assertSame(1, DB::table('sales')->count());
            $this->assertSame(1, DB::table('products')->value('stock'));
            $this->assertEquals(900, DB::table('sales')->value('total_cents'));
            $this->assertEquals(10900, Ledger::expected(1));
        });
        $body['request_key'] = Str::uuid()->toString();
        $this->post('/ventas', $body)->assertSessionHasErrors('operation');
        $t->run(fn () => $this->assertSame(1, DB::table('sales')->count()));
        $this->get('/ventas/1/ticket')->assertOk()->assertSee('9.00');
    }

    public function test_refund_on_later_shift_preserves_original_cash_close(): void
    {
        $t = $this->store();
        $pid = $this->product($t);
        $session = $this->sessionFor($t);
        $this->withSession($session)->post('/caja/open', ['opening' => '100.00']);
        $this->post('/ventas', ['request_key' => Str::uuid()->toString(), 'payment' => 'cash', 'received' => '10.00', 'items' => [['product_id' => $pid, 'quantity' => 2]]])->assertRedirect();
        $this->post('/caja/close', ['counted' => '110.00'])->assertRedirect();
        $this->post('/caja/open', ['opening' => '50.00']);
        $this->post('/devoluciones', ['sale_id' => 1, 'reason' => 'Devolución de prueba'])->assertSessionHasNoErrors();
        $this->post('/devoluciones', ['sale_id' => 1, 'reason' => 'Segundo intento'])->assertSessionHasErrors('operation');
        $t->run(function () {
            $this->assertEquals(11000, Ledger::expected(1));
            $this->assertEquals(4000, Ledger::expected(2));
            $this->assertSame(10, DB::table('products')->value('stock'));
            $this->assertSame(1, DB::table('sale_returns')->count());
        });
    }

    public function test_purchase_adds_stock_and_historical_sale_cost_is_preserved(): void
    {
        $t = $this->store();
        $pid = $this->product($t);
        $this->withSession($this->sessionFor($t))->post('/caja/open', ['opening' => '0.00']);
        $this->post('/ventas', ['request_key' => Str::uuid()->toString(), 'payment' => 'card', 'items' => [['product_id' => $pid, 'quantity' => 1]]])->assertRedirect();
        $this->post('/compras', ['request_key' => Str::uuid()->toString(), 'reference' => 'F001-1', 'items' => [['product_id' => $pid, 'quantity' => 5, 'cost' => '4.00']]])->assertSessionHasNoErrors();
        $t->run(function () {
            $this->assertSame(14, DB::table('products')->value('stock'));
            $this->assertEquals(400, DB::table('products')->value('cost_cents'));
            $this->assertEquals(300, DB::table('sales')->value('cost_cents'));
        });
        $this->get('/compras/1')->assertOk();
    }

    public function test_seller_cannot_modify_inventory_manage_users_or_access_platform(): void
    {
        $t = $this->store();
        $uid = $t->run(fn () => StoreUser::create(['name' => 'Vendedor', 'username' => 'seller', 'email' => 'seller@test.local', 'password' => 'Password1234', 'role' => 'seller'])->id);
        $this->withSession($this->sessionFor($t, $uid))->get('/dashboard')->assertOk();
        $this->get('/inventario')->assertForbidden();
        $this->get('/catalogo/users')->assertForbidden();
        $this->post('/catalogo/products', ['name' => 'No autorizado'])->assertForbidden();
        $this->get('/reportes')->assertForbidden();
        $this->post('/respaldo')->assertForbidden();
        $this->get('/plataforma')->assertRedirect('/plataforma/login');
    }

    public function test_all_admin_screens_render_and_exports_have_correct_signatures(): void
    {
        $t = $this->store();
        $this->product($t);
        $this->withSession($this->sessionFor($t));
        foreach (['/dashboard', '/catalogo/products', '/catalogo/products/1/editar', '/catalogo/categories', '/catalogo/brands', '/catalogo/customers', '/catalogo/suppliers', '/catalogo/users', '/inventario', '/ventas', '/compras', '/operacion/sales', '/operacion/purchases', '/caja', '/devoluciones', '/auditoria', '/reportes', '/configuracion'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertStringStartsWith('%PDF-1.4', $this->get('/reportes?format=pdf')->assertOk()->getContent());
        $this->assertStringStartsWith('PK', $this->get('/reportes?format=xlsx')->assertOk()->getContent());
        $this->get('/reportes?format=csv')->assertOk()->assertSee('Venta');
    }

    public function test_platform_registration_creates_tenant_with_admin_and_subscription(): void
    {
        $user = new User(['name' => 'Propietario', 'email' => 'owner@test.local', 'password' => 'Password1234']);
        $user->is_platform_admin = true;
        $user->save();
        $this->post('/plataforma/login', ['identity' => $user->email, 'password' => 'Password1234'])->assertRedirect('/plataforma');
        $this->get('/plataforma')->assertOk();
        $this->post('/plataforma/negocios', ['code' => 'NEWSTORE', 'business_name' => 'Nuevo negocio', 'owner_name' => 'Propietario', 'document_number' => '12345678', 'email' => 'admin@test.local', 'username' => 'admin', 'password' => 'Password1234', 'password_confirmation' => 'Password1234', 'plan' => 'Básico', 'subscription_until' => today()->addMonth()->toDateString()])->assertSessionHasNoErrors();
        $t = Tenant::where('code', 'NEWSTORE')->first();
        $this->assertNotNull($t);
        $this->created[] = $t;
        $this->assertSame('ready', $t->provisioning_status);
        $this->assertSame('admin', $t->run(fn () => StoreUser::first()->role));
    }

    public function test_invalid_payments_discounts_and_negative_stock_do_not_change_ledger(): void
    {
        $t = $this->store();
        $pid = $this->product($t);
        $this->withSession($this->sessionFor($t));
        $body = ['request_key' => Str::uuid()->toString(), 'payment' => 'cash', 'received' => '0.01', 'items' => [['product_id' => $pid, 'quantity' => 1]]];
        $this->post('/ventas', $body)->assertSessionHasErrors('operation');
        $this->post('/caja/open', ['opening' => '0.00']);
        $this->post('/ventas', $body)->assertSessionHasErrors('operation');
        $body['received'] = '5.00';
        $body['discount'] = '8.00';
        $this->post('/ventas', $body)->assertSessionHasErrors('operation');
        $this->post('/inventario/ajuste', ['product_id' => $pid, 'quantity' => -20, 'reason' => 'Ajuste inválido'])->assertSessionHasErrors('operation');
        $t->run(function () {
            $this->assertSame(0, DB::table('sales')->count());
            $this->assertSame(10, DB::table('products')->value('stock'));
        });
    }
}
