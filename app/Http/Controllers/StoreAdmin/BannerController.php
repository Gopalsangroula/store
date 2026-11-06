<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\Media;
use Illuminate\Http\Request;

/** The promo cards under the home page hero. */
class BannerController extends Controller
{
    public function index()
    {
        return $this->page(new Banner(['is_active' => true, 'button_text' => 'Shop Now']));
    }

    public function create()
    {
        return $this->index();
    }

    public function edit(Banner $banner)
    {
        return $this->page($banner);
    }

    public function store(Request $request)
    {
        Banner::create($this->validated($request));

        return redirect()->route('admin.banners.index')->with('status', 'Banner added.');
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->update($this->validated($request));

        return redirect()->route('admin.banners.index')->with('status', 'Banner saved.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return back()->with('status', 'Banner deleted.');
    }

    private function page(Banner $editing)
    {
        return view('admin.marketing.banners', ['banners' => Banner::orderBy('sort')->get(), 'editing' => $editing]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'eyebrow' => 'nullable|string|max:80',
            'title' => 'required|string|max:120',
            'subtitle' => 'nullable|string|max:300',
            'link' => ['nullable', 'string', 'max:250', 'regex:~^(/|https?://)~'],
            'button_text' => 'nullable|string|max:40',
            'sort' => 'nullable|integer|min:0',
            'image' => 'nullable|'.Media::IMAGE_RULE,
        ], ['link.regex' => 'The link must start with / or https://']);

        if ($request->hasFile('image')) {
            $data['image_path'] = Media::store($request->file('image'), $this->currentStore()->id, 'banners');
        }

        unset($data['image']);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['position'] = 'feature';

        return $data;
    }
}
