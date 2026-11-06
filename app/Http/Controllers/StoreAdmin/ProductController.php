<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\HtmlSanitizer;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['images', 'category', 'variants.inventory'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$s}%"))))
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('sort')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create()
    {
        if ($limit = $this->currentStore()->plan?->max_products) {
            if (Product::count() >= $limit) {
                return redirect()->route('admin.products.index')->with('error', "Your plan allows {$limit} products. Upgrade to add more.");
            }
        }

        return $this->form(new Product(['status' => 'active', 'is_taxable' => true]));
    }

    public function edit(Product $product)
    {
        return $this->form($product->load('variants.inventory', 'images'));
    }

    public function store(Request $request, InventoryService $inventory)
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request, $inventory) {
            $product = Product::create($data['product']);
            $this->saveVariants($product, $data['variants'], $inventory);
            $this->saveImages($product, $request);

            return $product;
        });

        ActivityLog::record('product.created', $product);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function update(Request $request, Product $product, InventoryService $inventory)
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($product, $data, $request, $inventory) {
            $product->update($data['product']);
            $this->saveVariants($product, $data['variants'], $inventory);
            $this->saveImages($product, $request);
        });

        ActivityLog::record('product.updated', $product);

        return back()->with('status', 'Product saved.');
    }

    public function destroy(Product $product)
    {
        // Soft delete: past orders keep their snapshot, and the product can be restored by support.
        $product->update(['status' => 'archived']);
        $product->delete();
        ActivityLog::record('product.deleted', $product);

        return redirect()->route('admin.products.index')->with('status', 'Product removed from the store.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $image->delete();

        return back()->with('status', 'Image removed.');
    }

    private function form(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'variants' => $product->exists
                ? $product->variants->map(fn (ProductVariant $v) => $v->only('id', 'name', 'sku', 'price', 'compare_at_price', 'cost_price', 'swatch', 'track_inventory', 'allow_preorder', 'is_active') + ['stock' => $v->onHand(), 'opening_stock' => null])
                : collect([['id' => null, 'name' => '', 'sku' => '', 'price' => '', 'compare_at_price' => '', 'cost_price' => '', 'swatch' => '', 'track_inventory' => true, 'allow_preorder' => false, 'is_active' => true, 'stock' => 0, 'opening_stock' => 0]]),
        ]);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $storeId = $this->currentStore()->id;

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', Rule::unique('products', 'slug')->where('store_id', $storeId)->ignore($product?->id)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('store_id', $storeId)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('store_id', $storeId)],
            'badge' => 'nullable|string|max:60',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:60000',
            'status' => 'required|in:draft,active,archived',
            'is_featured' => 'boolean',
            'is_taxable' => 'boolean',
            'seo_title' => 'nullable|string|max:200',
            'seo_description' => 'nullable|string|max:500',
            'sort' => 'nullable|integer|min:0|max:65000',
            'images.*' => Media::IMAGE_RULE,
            'variants' => 'required|array|min:1|max:100',
            'variants.*.id' => 'nullable|integer',
            'variants.*.name' => 'nullable|string|max:120',
            'variants.*.sku' => 'required|string|max:100|distinct',
            'variants.*.price' => 'required|numeric|min:0|max:99999999',
            'variants.*.compare_at_price' => 'nullable|numeric|min:0|max:99999999',
            'variants.*.cost_price' => 'nullable|numeric|min:0|max:99999999',
            'variants.*.swatch' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'variants.*.track_inventory' => 'boolean',
            'variants.*.allow_preorder' => 'boolean',
            'variants.*.is_active' => 'boolean',
            'variants.*.opening_stock' => 'nullable|integer|min:0|max:1000000',
            'variants.*.image' => Media::IMAGE_RULE.'|nullable',
        ], ['variants.*.sku.distinct' => 'Each variant needs its own SKU.']);

        // SKUs are unique per store, across all products.
        foreach ($data['variants'] as $i => $variant) {
            $clash = ProductVariant::withTrashed()->where('sku', $variant['sku'])
                ->when($variant['id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))->exists();

            if ($clash) {
                throw \Illuminate\Validation\ValidationException::withMessages(["variants.{$i}.sku" => "SKU {$variant['sku']} is already used by another product."]);
            }
        }

        $productFields = collect($data)->only(['name', 'category_id', 'brand_id', 'badge', 'short_description', 'status', 'seo_title', 'seo_description'])->all();
        $productFields['slug'] = $data['slug'] ?: $this->uniqueSlug($data['name'], $product);
        $productFields['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        $productFields['is_featured'] = $request->boolean('is_featured');
        $productFields['is_taxable'] = $request->boolean('is_taxable');
        $productFields['sort'] = (int) ($data['sort'] ?? 0);

        return ['product' => $productFields, 'variants' => $data['variants']];
    }

    private function saveVariants(Product $product, array $rows, InventoryService $inventory): void
    {
        $keep = [];
        $warehouse = null;

        foreach (array_values($rows) as $sort => $row) {
            $fields = [
                'name' => $row['name'] ?: null,
                'sku' => strtoupper(trim($row['sku'])),
                'price' => $row['price'],
                'compare_at_price' => $row['compare_at_price'] ?: null,
                'cost_price' => ($row['cost_price'] ?? '') !== '' ? $row['cost_price'] : null,
                'swatch' => $row['swatch'] ?: null,
                'track_inventory' => (bool) ($row['track_inventory'] ?? false),
                'allow_preorder' => (bool) ($row['allow_preorder'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'sort' => $sort,
            ];

            $variant = ! empty($row['id']) ? $product->variants()->find($row['id']) : null;

            if ($variant) {
                $variant->update($fields);
            } else {
                $variant = $product->variants()->create($fields);

                if ((int) ($row['opening_stock'] ?? 0) > 0 && $variant->track_inventory) {
                    $warehouse ??= $inventory->defaultWarehouse();
                    $inventory->adjust($variant, $warehouse, (int) $row['opening_stock'], 'Opening stock', $variant->cost_price ? (float) $variant->cost_price : null);
                }
            }

            if (($row['image'] ?? null) instanceof \Illuminate\Http\UploadedFile) {
                $variant->update(['image_path' => Media::store($row['image'], $product->store_id, 'products')]);
            }

            $keep[] = $variant->id;
        }

        // Removed rows are soft-deleted so old orders and stock history still resolve.
        $product->variants()->whereNotIn('id', $keep)->get()->each->delete();
    }

    private function saveImages(Product $product, Request $request): void
    {
        $next = (int) $product->images()->max('sort') + 1;

        foreach ((array) $request->file('images', []) as $file) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => Media::store($file, $product->store_id, 'products'),
                'alt' => $product->name,
                'sort' => $next++,
            ]);
        }
    }

    private function uniqueSlug(string $name, ?Product $product): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;

        while (Product::withTrashed()->where('slug', $slug)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
