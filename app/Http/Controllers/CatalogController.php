<?php

namespace App\Http\Controllers;

use App\Models\StoreUser;
use App\Services\Access;
use App\Services\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public const MODULES = ['products' => 'Productos', 'categories' => 'Categorías', 'brands' => 'Marcas', 'customers' => 'Clientes', 'suppliers' => 'Proveedores', 'users' => 'Usuarios'];

    private function module(string $module, bool $write = false): void
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        Access::check($module);
        if ($write && $module === 'products') {
            Access::check('inventory');
        }
    }

    public function index(Request $r, string $module)
    {
        $this->module($module);
        $q = DB::table($module);
        $search = trim((string) $r->query('q', ''));
        if ($search !== '') {
            $q->where(function ($q) use ($module, $search) {
                $q->where('name', 'like', "%$search%");
                if ($module === 'products') {
                    $q->orWhere('sku', 'like', "%$search%")->orWhere('barcode', 'like', "%$search%");
                }
                if (in_array($module, ['customers', 'suppliers'])) {
                    $q->orWhere('document', 'like', "%$search%");
                }
            });
        }
        $rows = $q->orderByDesc('id')->paginate(15)->withQueryString();
        if ($module === 'users') {
            $rows->getCollection()->transform(fn ($u) => tap($u, function ($u) {
                unset($u->password,$u->remember_token);
            }));
        }

        return view('store.catalog', ['module' => $module, 'title' => self::MODULES[$module], 'rows' => $rows, 'record' => null, 'editing' => false, 'categories' => DB::table('categories')->pluck('name', 'id'), 'brands' => DB::table('brands')->pluck('name', 'id')]);
    }

    public function edit(string $module, int $id)
    {
        $this->module($module, true);
        $record = DB::table($module)->find($id);
        abort_unless($record, 404);
        if ($module === 'users') {
            unset($record->password,$record->remember_token);
        }

        return view('store.catalog', ['module' => $module, 'title' => 'Editar '.self::MODULES[$module], 'record' => $record, 'editing' => true, 'categories' => DB::table('categories')->pluck('name', 'id'), 'brands' => DB::table('brands')->pluck('name', 'id')]);
    }

    public function save(Request $r, string $module, ?int $id = null)
    {
        $this->module($module, true);
        if ($id) {
            abort_unless(DB::table($module)->where('id', $id)->exists(), 404);
        }
        $rules = ['name' => ['required', 'string', 'max:150']];
        if (in_array($module, ['categories', 'brands'])) {
            $rules['name'][] = Rule::unique($module, 'name')->ignore($id);
        }
        if (in_array($module, ['customers', 'suppliers'])) {
            $rules += [
                'document' => ['nullable', 'string', 'max:20', Rule::unique($module, 'document')->ignore($id)],
                'email' => 'nullable|email|max:150', 'phone' => 'nullable|string|max:30', 'address' => 'nullable|string|max:200',
            ];
        }
        if ($module === 'products') {
            $rules += [
                'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($id)],
                'barcode' => ['nullable', 'string', 'max:80', Rule::unique('products', 'barcode')->ignore($id)],
                'category_id' => 'nullable|integer|exists:categories,id', 'brand_id' => 'nullable|integer|exists:brands,id',
                'cost' => 'required|regex:/^\d{1,8}(\.\d{1,2})?$/', 'price' => 'required|regex:/^\d{1,8}(\.\d{1,2})?$/',
                'min_stock' => 'required|integer|min:0|max:1000000', 'active' => 'required|boolean',
            ];
        }
        if ($module === 'users') {
            $rules += [
                'username' => ['required', 'regex:/^[A-Za-z0-9_.-]{3,40}$/', Rule::unique('users', 'username')->ignore($id)],
                'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
                'password' => ($id ? 'nullable' : 'required').'|string|min:10|max:128|confirmed', 'role' => 'required|in:admin,seller,cashier', 'active' => 'required|boolean',
            ];
        }
        $data = $r->validate($rules);
        DB::transaction(function () use ($data, $module, $id) {
            DB::table('business_settings')->lockForUpdate()->first();
            $data['updated_at'] = now();
            if ($module === 'products') {
                $data['cost_cents'] = Ledger::cents($data['cost']);
                $data['price_cents'] = Ledger::cents($data['price']);
                unset($data['cost'],$data['price']);
            }
            if ($module === 'users') {
                if ($id) {
                    $current = StoreUser::findOrFail($id);
                    if ($current->id === request()->attributes->get('storeUser')->id && (! $data['active'] || $data['role'] !== 'admin')) {
                        Ledger::fail('No puedes desactivar tu propia cuenta ni quitarte el rol de administrador.');
                    }
                    if ($current->role === 'admin' && (! $data['active'] || $data['role'] !== 'admin') && StoreUser::where('role', 'admin')->where('active', true)->count() <= 1) {
                        Ledger::fail('Debe permanecer un administrador activo.');
                    }
                }
                if (empty($data['password'])) {
                    unset($data['password']);
                }
                if ($id) {
                    StoreUser::findOrFail($id)->update($data);
                } else {
                    StoreUser::create($data);
                }
            } else {
                if ($id) {
                    DB::table($module)->where('id', $id)->update($data);
                } else {
                    $data['created_at'] = now();
                    DB::table($module)->insert($data);
                }
            }
            Ledger::audit("$module.saved", $data['name']);
        });

        return redirect()->route('catalog.index', $module)->with('success', 'Registro guardado.');
    }
}
