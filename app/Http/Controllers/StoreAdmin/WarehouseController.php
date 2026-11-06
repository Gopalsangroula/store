<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        return $this->page(new Warehouse(['is_active' => true]));
    }

    public function create()
    {
        return $this->page(new Warehouse(['is_active' => true]));
    }

    public function edit(Warehouse $warehouse)
    {
        return $this->page($warehouse);
    }

    public function store(Request $request)
    {
        $this->save(new Warehouse, $request);

        return redirect()->route('admin.warehouses.index')->with('status', 'Warehouse added.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->save($warehouse, $request);

        return redirect()->route('admin.warehouses.index')->with('status', 'Warehouse saved.');
    }

    private function save(Warehouse $warehouse, Request $request): void
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('warehouses', 'code')->where('store_id', $this->currentStore()->id)->ignore($warehouse->id)],
            'address' => 'nullable|string|max:250',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
        $data['code'] = strtoupper($data['code']);

        DB::transaction(function () use ($warehouse, $data) {
            if (! empty($data['is_default'])) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
                $data['is_active'] = true;
            }

            $warehouse->fill($data)->save();

            if (! Warehouse::where('is_default', true)->exists()) {
                Warehouse::orderBy('id')->first()->update(['is_default' => true]);
            }
        });
    }

    private function page(Warehouse $editing)
    {
        return view('admin.catalog.simple', [
            'title' => 'Warehouses',
            'intro' => 'Places you keep stock. Orders take stock from the default warehouse first.',
            'rows' => Warehouse::orderByDesc('is_default')->orderBy('name')->get(),
            'columns' => ['name' => 'Name', 'code' => 'Code', 'address' => 'Address', 'is_default' => 'Default', 'is_active' => 'Active'],
            'editing' => $editing,
            'fields' => ['name' => ['Name', 'text', true], 'code' => ['Short code', 'text', true], 'address' => ['Address', 'text', false], 'is_default' => ['Default warehouse for orders', 'checkbox', false], 'is_active' => ['Active', 'checkbox', false]],
            'routes' => ['index' => 'admin.warehouses.index', 'store' => 'admin.warehouses.store', 'update' => 'admin.warehouses.update', 'edit' => 'admin.warehouses.edit'],
            'singular' => 'warehouse',
        ]);
    }
}
