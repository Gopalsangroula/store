<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = ProductReview::with('product')
            ->when($request->status === 'waiting', fn ($q) => $q->where('is_approved', false))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.catalog.reviews', ['reviews' => $reviews, 'waiting' => ProductReview::where('is_approved', false)->count()]);
    }

    public function update(Request $request, ProductReview $review)
    {
        $review->update(['is_approved' => $request->boolean('is_approved')]);

        return back()->with('status', $review->is_approved ? 'Review published.' : 'Review hidden.');
    }

    public function destroy(ProductReview $review)
    {
        $review->delete();

        return back()->with('status', 'Review deleted.');
    }
}
