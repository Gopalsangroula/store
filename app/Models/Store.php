<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'owner_id', 'plan_id', 'status', 'suspended_reason', 'base_currency', 'timezone',
        'email', 'phone', 'whatsapp', 'address', 'pan_vat_number', 'logo_path', 'trial_ends_at',
    ];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime'];
    }

    private ?array $settingsCache = null;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(StoreSubscription::class)->where('status', 'active')->latestOfMany('ends_at');
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function primaryDomain(): string
    {
        $domains = $this->relationLoaded('domains') ? $this->domains : $this->domains()->get();

        return $domains->firstWhere('is_primary', true)?->domain
            ?? $domains->first()?->domain
            ?? $this->slug.'.'.config('platform.domain');
    }

    public function url(string $path = '/'): string
    {
        $host = $this->primaryDomain();
        $local = str_ends_with(explode(':', $host)[0], 'localhost');

        return ($local ? 'http' : 'https').'://'.$host.'/'.ltrim($path, '/');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $this->settingsCache ??= StoreSetting::withoutGlobalScopes()
            ->where('store_id', $this->id)->pluck('value', 'key')->all();

        $value = $this->settingsCache[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function setSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            StoreSetting::withoutGlobalScopes()->updateOrCreate(
                ['store_id' => $this->id, 'key' => $key],
                ['value' => is_bool($value) ? (int) $value : $value],
            );
        }

        $this->settingsCache = null;
    }

    public function logoUrl(): ?string
    {
        return \App\Support\Media::url($this->logo_path);
    }

    /** Plan features: a plan without a feature list allows everything. */
    public function hasFeature(string $feature): bool
    {
        $features = $this->plan?->features;

        return $features === null || in_array($feature, $features, true);
    }
}
