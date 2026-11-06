<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** About, contact, return and privacy pages (and any others) linked from the footer. */
class PageController extends Controller
{
    private const RESERVED = ['shop', 'cart', 'checkout', 'track', 'account', 'admin', 'superadmin', 'api', 'order', 'product', 'category', 'payment', 'currency', 'sitemap', 'storage', 'vendor', 'css', 'up'];

    public function index()
    {
        return view('admin.marketing.pages', ['pages' => Page::orderBy('title')->get()]);
    }

    public function create()
    {
        return view('admin.marketing.page-form', ['page' => new Page(['is_published' => true, 'in_footer' => true])]);
    }

    public function edit(Page $page)
    {
        return view('admin.marketing.page-form', ['page' => $page]);
    }

    public function store(Request $request)
    {
        Page::create($this->validated($request));

        return redirect()->route('admin.pages.index')->with('status', 'Page created.');
    }

    public function update(Request $request, Page $page)
    {
        $page->update($this->validated($request, $page));

        return redirect()->route('admin.pages.index')->with('status', 'Page saved.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return back()->with('status', 'Page deleted.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $request->merge(['slug' => Str::slug($request->slug ?: $request->title)]);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'slug' => ['required', 'max:150', Rule::notIn(self::RESERVED), Rule::unique('pages', 'slug')->where('store_id', $this->currentStore()->id)->ignore($page?->id)],
            'content' => 'nullable|string|max:100000',
        ], ['slug.not_in' => 'That address is used by the store itself. Choose another.']);

        $data['content'] = HtmlSanitizer::clean($data['content'] ?? '');
        $data['is_published'] = $request->boolean('is_published');
        $data['in_footer'] = $request->boolean('in_footer');

        return $data;
    }
}
