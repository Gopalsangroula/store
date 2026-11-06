<?php

namespace App\Models;

use App\Support\Media;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'position', 'eyebrow', 'title', 'subtitle', 'image_path', 'link', 'button_text', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image_path);
    }
}
