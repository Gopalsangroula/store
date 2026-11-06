<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return $this->page(new Category(['is_active' => true]));
    }

    public function create()
    {
        return $this->page(new Category(['is_active' => true]));
    }

    public function edit(Category $category)
    {
        return $this->page($category);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('status', 'Category added.');
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('admin.categories.index')->with('status', 'Category saved.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Move the products out of this category first.');
        }

        $category->children()->update(['parent_id' => null]);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    private function page(Category $editing)
    {
        return view('admin.catalog.categories', [
            'categories' => Category::withCount('products')->with('parent')->orderBy('sort')->orderBy('name')->get(),
            'editing' => $editing,
        ]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $storeId = $this->currentStore()->id;
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('categories', 'slug')->where('store_id', $storeId)->ignore($category?->id)],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where('store_id', $storeId), Rule::notIn([$category?->id])],
            'description' => 'nullable|string|max:2000',
            'sort' => 'nullable|integer|min:0|max:65000',
            'image' => 'nullable|'.Media::IMAGE_RULE,
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            $data['image_path'] = Media::store($request->file('image'), $storeId, 'categories');
        }

        unset($data['image']);

        return $data;
    }
}
