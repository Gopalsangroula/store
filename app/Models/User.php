<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person who runs stores: the platform super admin, store owners and their staff.
 * Shoppers are Customers, not Users.
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_super_admin', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['status' => 'active', 'is_super_admin' => false];

    /** @var array<string, StoreStaff|false> */
    private array $membershipCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function staffMemberships(): HasMany
    {
        return $this->hasMany(StoreStaff::class)->withoutGlobalScopes();
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_staff')->withPivot('role_id', 'status');
    }

    public function membershipFor(Store $store): ?StoreStaff
    {
        $key = (string) $store->id;

        if (! array_key_exists($key, $this->membershipCache)) {
            $this->membershipCache[$key] = StoreStaff::withoutGlobalScopes()
                ->where('user_id', $this->id)
                ->where('store_id', $store->id)
                ->where('status', 'active')
                ->with('role.permissions')
                ->first() ?? false;
        }

        return $this->membershipCache[$key] ?: null;
    }

    /**
     * Whether this user may do $permission in $store. Owners can do everything in their own
     * store; everyone else needs the permission on their role in that same store.
     */
    public function canInStore(Store $store, string $permission): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $membership = $this->membershipFor($store);

        if (! $membership) {
            return false;
        }

        return $membership->role->slug === 'owner'
            || $membership->role->permissions->contains('slug', $permission);
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
