<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/** Public, read-only catalog of the store found from the host name. */
class CatalogController extends Controller
{
    public function products(Request $request)
    {
        $products = Product::visible()->with(['images', 'activeVariants.inventory', 'category'])
            ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->orderBy('sort')->paginate(min(100, max(1, (int) $request->input('per_page', 24))));

        return response()->json([
            'data' => $products->getCollection()->map(fn ($p) => $this->present($p)),
            'meta' => ['page' => $products->currentPage(), 'pages' => $products->lastPage(), 'total' => $products->total(), 'currency' => $this->currentStore()->base_currency],
        ]);
    }

    public function product(string $slug)
    {
        $product = Product::visible()->where('slug', $slug)->with(['images', 'activeVariants.inventory', 'category'])->firstOrFail();

        return response()->json(['data' => $this->present($product) + ['description' => \App\Support\HtmlSanitizer::clean($product->description)]]);
    }

    private function present(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'badge' => $p->badge,
            'summary' => $p->short_description,
            'category' => $p->category?->slug,
            'url' => $this->currentStore()->url('/product/'.$p->slug),
            'images' => $p->images->map(fn ($i) => url($i->url()))->values(),
            'variants' => $p->activeVariants->map(fn (ProductVariant $v) => [
                'id' => $v->id, 'name' => $v->name, 'sku' => $v->sku, 'price' => (float) $v->price,
                'compare_at_price' => $v->compare_at_price ? (float) $v->compare_at_price : null,
                'availability' => $v->stockState(),
            ])->values(),
        ];
    }

    public function categories()
    {
        return response()->json(['data' => Category::where('is_active', true)->orderBy('sort')->get(['id', 'parent_id', 'name', 'slug'])]);
    }
}
