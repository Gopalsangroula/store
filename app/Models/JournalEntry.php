<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use BelongsToStore;

    public const UPDATED_AT = null;

    protected $fillable = ['store_id', 'entry_date', 'memo', 'reference_type', 'reference_id', 'user_id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Posted journal entries cannot be changed; post a reversing entry.'));
        static::deleting(fn () => throw new \LogicException('Posted journal entries cannot be deleted; post a reversing entry.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
