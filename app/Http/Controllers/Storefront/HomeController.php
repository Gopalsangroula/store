<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::visible()
            ->with(['images', 'activeVariants.inventory', 'category'])
            ->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('id')
            ->limit(24)
            ->get();

        return view('storefront.home', [
            'products' => $products,
            'banners' => Banner::where('is_active', true)->where('position', 'feature')->orderBy('sort')->limit(2)->get(),
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort')->withCount(['products' => fn ($q) => $q->visible()])->get(),
            'bestSeller' => $products->firstWhere('badge', 'Best seller') ?? $products->first(),
        ]);
    }
}
