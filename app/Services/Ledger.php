<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ledger
{
    public static function cents(mixed $value): int
    {
        $s = (string) $value;
        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $s)) {
            self::fail('Importe inválido. Usa hasta dos decimales y un máximo de 99999999.99.');
        }
        $parts = explode('.', $s);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    public static function audit(string $action, string $detail): void
    {
        DB::table('audit_logs')->insert(['user_id' => request()->attributes->get('storeUser')?->id, 'action' => $action, 'detail' => $detail, 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function movement(int $product, int $quantity, int $balance, string $type, string $reference, int $user): void
    {
        DB::table('stock_movements')->insert(['product_id' => $product, 'user_id' => $user, 'type' => $type, 'quantity' => $quantity, 'balance' => $balance, 'reference' => $reference, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function cash()
    {
        DB::table('business_settings')->lockForUpdate()->first();
        $cash = DB::table('cash_sessions')->whereNull('closed_at')->lockForUpdate()->first();
        if (! $cash) {
            self::fail('Abre la caja antes de registrar esta operación.');
        }

        return $cash;
    }

    public function sale(array $data, int $user): int
    {
        return DB::transaction(function () use ($data, $user) {
            $cash = self::cash();
            $existing = DB::table('sales')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                return $existing->id;
            }
            $lines = $this->lines($data['items']);
            $subtotal = 0;
            $cost = 0;
            $items = [];
            foreach ($lines as $id => $quantity) {
                $p = DB::table('products')->where('id', $id)->where('active', true)->lockForUpdate()->first();
                if (! $p) {
                    self::fail('Uno de los productos ya no está disponible.');
                }
                if ($p->stock < $quantity) {
                    self::fail("Stock insuficiente para {$p->name}. Disponible: {$p->stock}.");
                }
                $subtotal += $p->price_cents * $quantity;
                $cost += $p->cost_cents * $quantity;
                $items[] = [$p, $quantity];
            }
            $discount = self::cents($data['discount'] ?? '0');
            if ($discount > $subtotal) {
                self::fail('El descuento no puede superar el subtotal.');
            }
            $total = $subtotal - $discount;
            $received = $data['payment'] === 'cash' ? self::cents($data['received'] ?? '0') : $total;
            if ($received < $total) {
                self::fail('El importe recibido es menor al total de la venta.');
            }
            $id = DB::table('sales')->insertGetId([
                'request_key' => $data['request_key'], 'customer_id' => $data['customer_id'] ?? null, 'user_id' => $user, 'cash_session_id' => $cash->id,
                'payment' => $data['payment'], 'subtotal_cents' => $subtotal, 'discount_cents' => $discount, 'total_cents' => $total, 'cost_cents' => $cost,
                'received_cents' => $received, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($items as [$p,$q]) {
                DB::table('sale_items')->insert(['sale_id' => $id, 'product_id' => $p->id, 'product_name' => $p->name, 'quantity' => $q, 'price_cents' => $p->price_cents, 'cost_cents' => $p->cost_cents]);
                DB::table('products')->where('id', $p->id)->update(['stock' => $p->stock - $q, 'updated_at' => now()]);
                self::movement($p->id, -$q, $p->stock - $q, 'sale', "Venta #$id", $user);
            }
            self::audit('sale.created', "Venta #$id · total $total céntimos");

            return $id;
        }, 3);
    }

    public function purchase(array $data, int $user): int
    {
        return DB::transaction(function () use ($data, $user) {
            DB::table('business_settings')->lockForUpdate()->first();
            $existing = DB::table('purchases')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                return $existing->id;
            }
            $ids = [];
            $items = [];
            $total = 0;
            usort($data['items'], fn ($a, $b) => (int) $a['product_id'] <=> (int) $b['product_id']);
            foreach ($data['items'] as $line) {
                $pid = (int) $line['product_id'];
                if (isset($ids[$pid])) {
                    self::fail('Agrega cada producto una sola vez en la compra.');
                }
                $ids[$pid] = true;
                $p = DB::table('products')->where('id', $pid)->where('active', true)->lockForUpdate()->first();
                if (! $p) {
                    self::fail('Producto no disponible.');
                }
                $q = (int) $line['quantity'];
                $cost = self::cents($line['cost']);
                if ($p->stock + $q > 1000000000) {
                    self::fail('La cantidad supera el stock máximo permitido.');
                }
                $total += $q * $cost;
                $items[] = [$p, $q, $cost];
            }
            $id = DB::table('purchases')->insertGetId(['request_key' => $data['request_key'], 'supplier_id' => $data['supplier_id'] ?? null, 'user_id' => $user, 'reference' => $data['reference'] ?? null, 'total_cents' => $total, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($items as [$p,$q,$cost]) {
                DB::table('purchase_items')->insert(['purchase_id' => $id, 'product_id' => $p->id, 'quantity' => $q, 'cost_cents' => $cost]);
                DB::table('products')->where('id', $p->id)->update(['stock' => $p->stock + $q, 'cost_cents' => $cost, 'updated_at' => now()]);
                self::movement($p->id, $q, $p->stock + $q, 'purchase', "Compra #$id", $user);
            }
            self::audit('purchase.created', "Compra #$id");

            return $id;
        }, 3);
    }

    public function refund(int $saleId, string $reason, int $user): void
    {
        DB::transaction(function () use ($saleId, $reason, $user) {
            $cash = self::cash();
            $sale = DB::table('sales')->where('id', $saleId)->lockForUpdate()->first();
            if (! $sale || $sale->status !== 'completed') {
                self::fail('Esta venta no está disponible para devolución.');
            }
            if ($sale->payment === 'cash' && self::expected($cash->id) < $sale->total_cents) {
                self::fail('El efectivo de caja no alcanza para este reembolso. Registra un ingreso de efectivo antes de devolver la venta.');
            }
            foreach (DB::table('sale_items')->where('sale_id', $saleId)->orderBy('product_id')->get() as $item) {
                $p = DB::table('products')->where('id', $item->product_id)->lockForUpdate()->first();
                DB::table('products')->where('id', $p->id)->update(['stock' => $p->stock + $item->quantity, 'updated_at' => now()]);
                self::movement($p->id, $item->quantity, $p->stock + $item->quantity, 'return', "Devolución venta #$saleId", $user);
            }
            DB::table('sale_returns')->insert(['sale_id' => $saleId, 'user_id' => $user, 'cash_session_id' => $cash->id, 'reason' => $reason, 'total_cents' => $sale->total_cents, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('sales')->where('id', $saleId)->update(['status' => 'returned', 'updated_at' => now()]);
            self::audit('sale.returned', "Venta #$saleId · $reason");
        }, 3);
    }

    public static function expected(int $cashId): int
    {
        $opening = DB::table('cash_sessions')->where('id', $cashId)->value('opening_cents');
        $sales = DB::table('sales')->where('cash_session_id', $cashId)->where('payment', 'cash')->sum('total_cents');
        $refunds = DB::table('sale_returns as r')->join('sales as s', 's.id', '=', 'r.sale_id')->where('r.cash_session_id', $cashId)->where('s.payment', 'cash')->sum('r.total_cents');
        $in = DB::table('cash_movements')->where('cash_session_id', $cashId)->where('type', 'income')->sum('amount_cents');
        $out = DB::table('cash_movements')->where('cash_session_id', $cashId)->where('type', 'expense')->sum('amount_cents');

        return $opening + $sales - $refunds + $in - $out;
    }

    private function lines(array $items): array
    {
        $lines = [];
        foreach ($items as $i) {
            $id = (int) $i['product_id'];
            $lines[$id] = ($lines[$id] ?? 0) + (int) $i['quantity'];
        }
        if (! $lines) {
            self::fail('Agrega al menos un producto.');
        }
        ksort($lines);

        return $lines;
    }
}
