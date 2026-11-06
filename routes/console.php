<?php

use App\Models\StoreUser;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger;
use App\Services\StoreProvisioner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ventas:administrador {email} {--name=Administrador}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Correo no válido.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('La cuenta ya existe. No se modificó.');

        return 1;
    }
    $password = Str::password(16);
    $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
    $user->is_platform_admin = true;
    $user->save();
    $this->info('Administrador de plataforma creado.');
    $this->line('Correo: '.$email);
    $this->line('Contraseña: '.$password);
    $this->line('Acceso: '.url('/plataforma/login'));
})->purpose('Crea al propietario de la plataforma sin modificar cuentas existentes');

Artisan::command('ventas:demo', function () {
    if (Tenant::where('code', 'DEMO')->exists()) {
        $this->info('La tienda DEMO ya existe. No se modificó.');

        return;
    }
    $password = Str::password(14);
    $store = app(StoreProvisioner::class)->create(['code' => 'DEMO', 'business_name' => 'Mi Tienda Demo', 'owner_name' => 'Administrador Demo', 'document_number' => '00000000',
        'email' => 'admin@demo.local', 'username' => 'admin', 'password' => $password, 'address' => 'Av. Los Emprendedores 120', 'phone' => '999 000 000',
        'plan' => 'Demostración', 'subscription_until' => now()->addYear()->toDateString()]);
    $store->run(function () {
        $now = now();
        $category = DB::table('categories')->insertGetId(['name' => 'Abarrotes', 'created_at' => $now, 'updated_at' => $now]);
        $beverages = DB::table('categories')->insertGetId(['name' => 'Bebidas', 'created_at' => $now, 'updated_at' => $now]);
        $cleaning = DB::table('categories')->insertGetId(['name' => 'Limpieza', 'created_at' => $now, 'updated_at' => $now]);
        $brand = DB::table('brands')->insertGetId(['name' => 'Marca general', 'created_at' => $now, 'updated_at' => $now]);
        $user = StoreUser::first()->id;
        foreach ([
            ['ARR-001', '7750000000011', 'Arroz extra 1 kg', 350, 450, 40, $category],
            ['ACE-001', '7750000000028', 'Aceite vegetal 1 L', 700, 900, 18, $category],
            ['AZU-001', '7750000000035', 'Azúcar rubia 1 kg', 320, 420, 35, $category],
            ['LEC-001', '7750000000042', 'Leche evaporada 400 g', 340, 450, 24, $category],
            ['AGU-001', '7750000000059', 'Agua sin gas 625 ml', 90, 150, 48, $beverages],
            ['JUG-001', '7750000000066', 'Jugo de durazno 1 L', 380, 550, 4, $beverages],
            ['DET-001', '7750000000073', 'Detergente 800 g', 650, 850, 3, $cleaning],
            ['FIDE-001', '7750000000080', 'Fideos spaghetti 500 g', 210, 320, 22, $category],
        ] as [$sku,$barcode,$name,$cost,$price,$stock,$cat]) {
            $id = DB::table('products')->insertGetId(['sku' => $sku, 'barcode' => $barcode, 'name' => $name, 'cost_cents' => $cost, 'price_cents' => $price, 'stock' => $stock, 'min_stock' => 5, 'category_id' => $cat, 'brand_id' => $brand, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            Ledger::movement($id, $stock, $stock, 'adjustment', 'Inventario inicial de demostración', $user);
        }
        DB::table('customers')->insert(['name' => 'Cliente de ejemplo', 'document' => '12345678', 'phone' => '999 111 222', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['name' => 'Distribuidora de ejemplo', 'document' => '20123456789', 'phone' => '999 333 444', 'created_at' => $now, 'updated_at' => $now]);
    });
    $this->info('Tienda de demostración creada con productos de ejemplo.');
    $this->line('Código: DEMO');
    $this->line('Usuario: admin');
    $this->line('Contraseña: '.$password);
})->purpose('Crea una tienda de demostración independiente con inventario de ejemplo');
