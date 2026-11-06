<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Cookie;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('storefront.page', ['page' => $page]);
    }

    public function currency(string $code, CurrencyService $currencies)
    {
        $code = strtoupper($code);

        if ($currencies->enabled()->contains('code', $code)) {
            Cookie::queue(Cookie::make(CurrencyService::COOKIE, $code, 60 * 24 * 365, null, null, null, true, false, 'lax'));
        }

        return back();
    }

    public function sitemap()
    {
        $urls = collect([route('shop.home'), route('shop.shop')])
            ->merge(Category::where('is_active', true)->pluck('slug')->map(fn ($slug) => route('shop.category', $slug)))
            ->merge(Product::visible()->pluck('slug')->map(fn ($slug) => route('shop.product', $slug)))
            ->merge(Page::where('is_published', true)->pluck('slug')->map(fn ($slug) => route('shop.page', $slug)));

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$urls->map(fn ($url) => '<url><loc>'.e($url).'</loc></url>')->implode('')
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
