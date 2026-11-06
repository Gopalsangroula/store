@extends('admin.layout', ['title' => 'Reviews'])

@section('content')
<div class="page-head">
    <div><h1>Reviews</h1><div class="sub">New reviews wait here until you publish them.</div></div>
    <div class="btn-group"><a href="{{ route('admin.reviews.index') }}" @class(['btn btn-light', 'active' => ! request('status')])>All</a><a href="{{ route('admin.reviews.index', ['status' => 'waiting']) }}" @class(['btn btn-light', 'active' => request('status') === 'waiting'])>Waiting ({{ $waiting }})</a></div>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Product</th><th>Review</th><th>Rating</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($reviews as $review)
        <tr>
            <td class="small fw-semibold">{{ $review->product?->name }}</td>
            <td><strong>{{ $review->name }}</strong> <span class="small text-muted">{{ $review->created_at->format('j M Y') }}</span><div class="small">{{ $review->body }}</div></td>
            <td class="text-warning text-nowrap">{{ str_repeat('★', $review->rating) }}</td>
            <td><span class="badge {{ $review->is_approved ? 'text-bg-success' : 'text-bg-warning' }}">{{ $review->is_approved ? 'Published' : 'Waiting' }}</span></td>
            <td class="text-end text-nowrap">
                <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="is_approved" value="{{ $review->is_approved ? 0 : 1 }}"><button class="btn btn-sm {{ $review->is_approved ? 'btn-light' : 'btn-accent' }}">{{ $review->is_approved ? 'Hide' : 'Publish' }}</button></form>
                <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="d-inline" onsubmit="return confirm('Delete this review?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">No reviews.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $reviews->links() }}</div>
@endsection
