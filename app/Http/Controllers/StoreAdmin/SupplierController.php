<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return $this->page(new Supplier);
    }

    public function create()
    {
        return $this->page(new Supplier);
    }

    public function edit(Supplier $supplier)
    {
        return $this->page($supplier);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier saved.');
    }

    public function destroy(Supplier $supplier)
    {
        if (PurchaseOrder::where('supplier_id', $supplier->id)->exists()) {
            return back()->with('error', 'This supplier has purchase orders, so it is kept for your records.');
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:250',
            'pan_vat_number' => 'nullable|string|max:30',
        ]);
    }

    private function page(Supplier $editing)
    {
        return view('admin.catalog.simple', [
            'title' => 'Suppliers',
            'intro' => 'Who you buy stock from.',
            'rows' => Supplier::orderBy('name')->get(),
            'columns' => ['name' => 'Name', 'contact_person' => 'Contact', 'phone' => 'Phone', 'pan_vat_number' => 'PAN/VAT'],
            'editing' => $editing,
            'fields' => ['name' => ['Name', 'text', true], 'contact_person' => ['Contact person', 'text', false], 'phone' => ['Phone', 'text', false], 'email' => ['Email', 'email', false], 'address' => ['Address', 'text', false], 'pan_vat_number' => ['PAN / VAT number', 'text', false]],
            'routes' => ['index' => 'admin.suppliers.index', 'store' => 'admin.suppliers.store', 'update' => 'admin.suppliers.update', 'edit' => 'admin.suppliers.edit', 'destroy' => 'admin.suppliers.destroy'],
            'singular' => 'supplier',
        ]);
    }
}
