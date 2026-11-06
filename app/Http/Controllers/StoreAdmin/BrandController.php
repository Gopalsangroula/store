<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index()
    {
        return $this->page(new Brand);
    }

    public function create()
    {
        return $this->page(new Brand);
    }

    public function edit(Brand $brand)
    {
        return $this->page($brand);
    }

    public function store(Request $request)
    {
        Brand::create($this->validated($request));

        return redirect()->route('admin.brands.index')->with('status', 'Brand added.');
    }

    public function update(Request $request, Brand $brand)
    {
        $brand->update($this->validated($request, $brand));

        return redirect()->route('admin.brands.index')->with('status', 'Brand saved.');
    }

    public function destroy(Brand $brand)
    {
        $brand->products()->update(['brand_id' => null]);
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('status', 'Brand deleted.');
    }

    private function page(Brand $editing)
    {
        return view('admin.catalog.simple', [
            'title' => 'Brands',
            'intro' => 'Brands shown on product pages.',
            'rows' => Brand::withCount('products')->orderBy('name')->get(),
            'columns' => ['name' => 'Name', 'products_count' => 'Products'],
            'editing' => $editing,
            'fields' => ['name' => ['Name', 'text', true]],
            'routes' => ['index' => 'admin.brands.index', 'store' => 'admin.brands.store', 'update' => 'admin.brands.update', 'edit' => 'admin.brands.edit', 'destroy' => 'admin.brands.destroy'],
            'singular' => 'brand',
        ]);
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $data['slug'] = Str::slug($data['name']);

        $request->merge(['slug' => $data['slug']])->validate([
            'slug' => [Rule::unique('brands', 'slug')->where('store_id', $this->currentStore()->id)->ignore($brand?->id)],
        ], ['slug.unique' => 'That brand already exists.']);

        return $data;
    }
}
