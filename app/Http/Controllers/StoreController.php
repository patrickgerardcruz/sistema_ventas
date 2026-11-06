<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Ledger;
use App\Services\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    private function user(): int
    {
        return request()->attributes->get('storeUser')->id;
    }

    public function dashboard()
    {
        Access::check('dashboard');
        $today = DB::table('sales')->whereDate('created_at', today())->where('status', 'completed');
        $revenue = (clone $today)->sum('total_cents');
        $profit = $revenue - (clone $today)->sum('cost_cents');
        $daily = DB::table('sales')->selectRaw('DATE(created_at) as day, SUM(total_cents) as total')->where('status', 'completed')->where('created_at', '>=', today()->subDays(6))->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $chart[] = ['label' => $date->format('d/m'), 'total' => (int) ($daily[$date->toDateString()] ?? 0)];
        }

        return view('store.dashboard', [
            'title' => 'Resumen del negocio', 'revenue' => $revenue, 'profit' => $profit, 'saleCount' => (clone $today)->count(),
            'productCount' => DB::table('products')->where('active', true)->count(),
            'lowStock' => DB::table('products')->where('active', true)->whereColumn('stock', '<=', 'min_stock')->orderBy('stock')->limit(6)->get(),
            'recent' => DB::table('sales')->orderByDesc('id')->limit(6)->get(), 'chart' => $chart,
            'cash' => DB::table('cash_sessions')->whereNull('closed_at')->first(),
        ]);
    }

    public function inventory()
    {
        Access::check('inventory');

        return view('store.inventory', ['title' => 'Inventario', 'products' => DB::table('products')->where('active', true)->orderBy('name')->get(),
            'movements' => DB::table('stock_movements as m')->join('products as p', 'p.id', '=', 'm.product_id')->join('users as u', 'u.id', '=', 'm.user_id')->select('m.*', 'p.name', 'u.name as operator')->orderByDesc('m.id')->paginate(20)]);
    }

    public function adjust(Request $r)
    {
        Access::check('inventory');
        $data = $r->validate(['product_id' => 'required|integer|exists:products,id', 'quantity' => 'required|integer|between:-1000000,1000000|not_in:0', 'reason' => 'required|string|min:5|max:180']);
        DB::transaction(function () use ($data) {
            $p = DB::table('products')->where('id', $data['product_id'])->lockForUpdate()->first();
            $balance = $p->stock + (int) $data['quantity'];
            if ($balance < 0 || $balance > 1000000000) {
                Ledger::fail('El ajuste debe dejar el stock entre 0 y 1000000000.');
            }
            DB::table('products')->where('id', $p->id)->update(['stock' => $balance, 'updated_at' => now()]);
            Ledger::movement($p->id, (int) $data['quantity'], $balance, 'adjustment', $data['reason'], $this->user());
            Ledger::audit('stock.adjusted', "{$p->name}: {$data['quantity']} · {$data['reason']}");
        }, 3);

        return back()->with('success', 'Ajuste de inventario registrado.');
    }

    public function sales(Request $r)
    {
        Access::check('sales');
        $q = DB::table('sales');
        if ($r->filled('customer')) {
            $q->where('customer_id', $r->integer('customer'));
        }

        return view('store.transactions', ['title' => 'Ventas', 'mode' => 'sales', 'rows' => $q->orderByDesc('id')->paginate(15)->withQueryString()]);
    }

    public function purchases(Request $r)
    {
        Access::check('purchases');
        $q = DB::table('purchases');
        if ($r->filled('supplier')) {
            $q->where('supplier_id', $r->integer('supplier'));
        }

        return view('store.transactions', ['title' => 'Compras', 'mode' => 'purchases', 'rows' => $q->orderByDesc('id')->paginate(15)->withQueryString()]);
    }

    public function order(string $mode)
    {
        abort_unless(in_array($mode, ['sales', 'purchases']), 404);
        Access::check($mode);
        $products = DB::table('products')->where('active', true)->orderBy('name')->get();
        if (! Access::allows('reports') && $mode === 'sales') {
            $products->transform(function ($p) {
                unset($p->cost_cents);

                return $p;
            });
        }

        return view('store.order', ['title' => $mode === 'sales' ? 'Nueva venta' : 'Nueva compra', 'mode' => $mode, 'products' => $products,
            'contacts' => DB::table($mode === 'sales' ? 'customers' : 'suppliers')->orderBy('name')->get(),
            'cashOpen' => DB::table('cash_sessions')->whereNull('closed_at')->exists()]);
    }

    public function saveSale(Request $r, Ledger $ledger)
    {
        Access::check('sales');
        $data = $r->validate(['request_key' => 'required|uuid', 'customer_id' => 'nullable|integer|exists:customers,id', 'payment' => 'required|in:cash,card,transfer',
            'discount' => 'nullable|regex:/^\d{1,8}(\.\d{1,2})?$/', 'received' => 'nullable|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:1000000']);
        $id = $ledger->sale($data, $this->user());

        return redirect()->route('ticket', $id)->with('success', 'Venta registrada correctamente.');
    }

    public function savePurchase(Request $r, Ledger $ledger)
    {
        Access::check('purchases');
        $data = $r->validate(['request_key' => 'required|uuid', 'supplier_id' => 'nullable|integer|exists:suppliers,id', 'reference' => 'nullable|string|max:80',
            'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:1000000', 'items.*.cost' => 'required|regex:/^\d{1,8}(\.\d{1,2})?$/']);
        $id = $ledger->purchase($data, $this->user());

        return redirect()->route('purchases')->with('success', "Compra #$id registrada. Stock actualizado.");
    }

    public function ticket(Request $r, int $id)
    {
        Access::check('sales');
        $sale = DB::table('sales')->find($id);
        abort_unless($sale, 404);

        return view('store.ticket', ['title' => "Venta #$id", 'sale' => $sale, 'items' => DB::table('sale_items')->where('sale_id', $id)->get(),
            'settings' => DB::table('business_settings')->first(), 'customer' => $sale->customer_id ? DB::table('customers')->find($sale->customer_id) : null,
            'width' => $r->query('width') === '58' ? '58' : '80']);
    }

    public function purchaseDetail(int $id)
    {
        Access::check('purchases');
        $purchase = DB::table('purchases')->find($id);
        abort_unless($purchase, 404);

        return view('store.purchase', ['title' => "Compra #$id", 'purchase' => $purchase,
            'supplier' => $purchase->supplier_id ? DB::table('suppliers')->find($purchase->supplier_id) : null,
            'items' => DB::table('purchase_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('purchase_id', $id)->select('i.*', 'p.name')->get()]);
    }

    public function cash()
    {
        Access::check('cash');
        $cash = DB::table('cash_sessions')->whereNull('closed_at')->first();

        return view('store.cash', ['title' => 'Caja', 'cash' => $cash, 'expected' => $cash ? Ledger::expected($cash->id) : 0,
            'movements' => $cash ? DB::table('cash_movements')->where('cash_session_id', $cash->id)->orderByDesc('id')->get() : collect(),
            'history' => DB::table('cash_sessions')->orderByDesc('id')->paginate(10)]);
    }

    public function cashAction(Request $r, string $action)
    {
        Access::check('cash');
        abort_unless(in_array($action, ['open', 'movement', 'close']), 404);
        $rules = $action === 'open' ? ['opening' => 'required|regex:/^\d{1,8}(\.\d{1,2})?$/'] : ($action === 'close' ? ['counted' => 'required|regex:/^\d{1,8}(\.\d{1,2})?$/'] : ['type' => 'required|in:income,expense', 'amount' => 'required|regex:/^\d{1,8}(\.\d{1,2})?$/', 'description' => 'required|string|min:3|max:180']);
        $data = $r->validate($rules);
        DB::transaction(function () use ($action, $data) {
            DB::table('business_settings')->lockForUpdate()->first();
            if ($action === 'open') {
                if (DB::table('cash_sessions')->whereNull('closed_at')->exists()) {
                    Ledger::fail('Ya existe un turno abierto.');
                }
                DB::table('cash_sessions')->insert(['user_id' => $this->user(), 'opening_cents' => Ledger::cents($data['opening']), 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $cash = Ledger::cash();
                if ($action === 'close') {
                    $expected = Ledger::expected($cash->id);
                    $counted = Ledger::cents($data['counted']);
                    DB::table('cash_sessions')->where('id', $cash->id)->update(['expected_cents' => $expected, 'counted_cents' => $counted, 'difference_cents' => $counted - $expected, 'closed_at' => now(), 'updated_at' => now()]);
                } else {
                    $amount = Ledger::cents($data['amount']);
                    if ($amount <= 0) {
                        Ledger::fail('El importe debe ser mayor a cero.');
                    }
                    if ($data['type'] === 'expense' && $amount > Ledger::expected($cash->id)) {
                        Ledger::fail('El egreso supera el efectivo esperado de caja.');
                    }
                    DB::table('cash_movements')->insert(['cash_session_id' => $cash->id, 'user_id' => $this->user(), 'type' => $data['type'], 'amount_cents' => $amount, 'description' => $data['description'], 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            Ledger::audit("cash.$action", 'Operación de caja registrada.');
        }, 3);

        return back()->with('success', 'Caja actualizada.');
    }

    public function returns()
    {
        Access::check('returns');

        return view('store.returns', ['title' => 'Devoluciones', 'returns' => DB::table('sale_returns')->orderByDesc('id')->paginate(15)]);
    }

    public function refund(Request $r, Ledger $ledger)
    {
        Access::check('returns');
        $data = $r->validate(['sale_id' => 'required|integer|exists:sales,id', 'reason' => 'required|string|min:5|max:200']);
        $ledger->refund($data['sale_id'], $data['reason'], $this->user());

        return back()->with('success', 'Devolución total registrada. Se repuso el stock y se registró el reembolso en el turno actual.');
    }

    public function audit()
    {
        Access::check('audit');

        return view('store.audit', ['title' => 'Auditoría', 'rows' => DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->select('a.*', 'u.name')->orderByDesc('a.id')->paginate(20)]);
    }

    public function settings()
    {
        Access::check('settings');

        return view('store.settings', ['title' => 'Configuración', 'settings' => DB::table('business_settings')->first()]);
    }

    public function saveSettings(Request $r)
    {
        Access::check('settings');
        $data = $r->validate(['business_name' => 'required|string|max:150', 'owner_name' => 'required|string|max:150', 'document_number' => 'required|digits:8', 'email' => 'required|email|max:150', 'phone' => 'nullable|string|max:20', 'address' => 'nullable|string|max:200']);
        DB::transaction(function () use ($data) {
            $settings = DB::table('business_settings')->lockForUpdate()->first();
            DB::table('business_settings')->where('id', $settings->id)->update($data + ['updated_at' => now()]);
            Ledger::audit('settings.updated', 'Datos del negocio actualizados.');
        });
        $r->attributes->get('store')->update(['name' => $data['business_name']]);

        return back()->with('success', 'Configuración guardada.');
    }

    public function reports(Request $r)
    {
        Access::check('reports');
        $data = $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'format' => 'nullable|in:csv,xlsx,pdf']);
        $from = $data['from'] ?? today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        if ($to < $from) {
            Ledger::fail('La fecha final debe ser igual o posterior a la inicial.');
        }
        $rows = DB::table('sales')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderByDesc('id')->get();
        if ($r->filled('format')) {
            return app(ReportExport::class)->download($rows, $data['format'], $from, $to);
        }
        $net = $rows->where('status', 'completed');

        return view('store.reports', ['title' => 'Reportes', 'rows' => $rows, 'from' => $from, 'to' => $to, 'net' => $net->sum('total_cents'), 'profit' => $net->sum('total_cents') - $net->sum('cost_cents')]);
    }

    public function backup()
    {
        Access::check('backups');
        Ledger::audit('backup.downloaded', 'Respaldo SQL del negocio descargado.');
        $sql = DB::transaction(function () {
            $tables = ['migrations', 'business_settings', 'users', 'categories', 'brands', 'customers', 'suppliers', 'products', 'cash_sessions', 'sales', 'sale_items', 'purchases', 'purchase_items', 'cash_movements', 'stock_movements', 'sale_returns', 'audit_logs'];
            $pdo = DB::connection()->getPdo();
            $sql = '-- Respaldo Nexo Ventas · '.now()."\n-- Tienda: ".request()->attributes->get('store')->code."\n-- Restaurar solo en una base vacía del mismo negocio.\nSET FOREIGN_KEY_CHECKS=0;\n";
            foreach ($tables as $table) {
                $schema = (array) DB::selectOne("SHOW CREATE TABLE `$table`");
                $sql .= array_values($schema)[1].";\n";
                foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                    $row = (array) $row;
                    $columns = implode(',', array_map(fn ($s) => "`$s`", array_keys($row)));
                    $values = implode(',', array_map(fn ($s) => $s === null ? 'NULL' : $pdo->quote((string) $s), array_values($row)));
                    $sql .= "INSERT INTO `$table` ($columns) VALUES ($values);\n";
                }
            }

            return $sql."SET FOREIGN_KEY_CHECKS=1;\n";
        });

        return response($sql, 200, ['Content-Type' => 'application/sql; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="respaldo-'.request()->attributes->get('store')->code.'-'.now()->format('Ymd-His').'.sql"', 'Cache-Control' => 'no-store']);
    }
}
