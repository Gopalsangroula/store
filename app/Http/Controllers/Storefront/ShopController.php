<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->listing($request, $category);
    }

    private function listing(Request $request, ?Category $category)
    {
        $search = trim((string) $request->query('q'));
        $sort = $request->query('sort', 'featured');

        $query = Product::visible()->with(['images', 'activeVariants.inventory']);

        if ($category) {
            $ids = $category->children()->pluck('id')->push($category->id);
            $query->whereIn('category_id', $ids);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where('name', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('name', 'like', $like));
            });
        }

        $priceSub = '(select min(price) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1 and product_variants.deleted_at is null)';

        match ($sort) {
            'price_asc' => $query->orderByRaw($priceSub.' asc'),
            'price_desc' => $query->orderByRaw($priceSub.' desc'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('id'),
        };

        return view('storefront.shop', [
            'products' => $query->paginate(24)->withQueryString(),
            'category' => $category,
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort')->get(),
            'search' => $search,
            'sort' => $sort,
        ]);
    }
}
