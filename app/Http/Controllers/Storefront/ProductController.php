<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::visible()->where('slug', $slug)
            ->with(['images', 'activeVariants.inventory', 'category', 'brand'])
            ->firstOrFail();

        $reviews = $product->reviews()->where('is_approved', true)->latest()->limit(20)->get();

        $related = Product::visible()->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['images', 'activeVariants.inventory'])
            ->limit(4)->get();

        return view('storefront.product', [
            'product' => $product,
            'reviews' => $reviews,
            'rating' => $reviews->avg('rating'),
            'related' => $related,
            'selected' => $product->activeVariants->firstWhere('id', (int) request('variant')) ?? $product->activeVariants->first(),
        ]);
    }

    public function review(Request $request, string $slug)
    {
        $product = Product::visible()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:1500',
        ]);

        // Every review waits for the shop to approve it; nothing goes live unchecked.
        ProductReview::create($data + [
            'product_id' => $product->id,
            'customer_id' => auth('customer')->id(),
            'is_approved' => false,
        ]);

        return back()->with('status', 'Thank you! Your review will appear after the shop approves it.');
    }
}
