<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A shopper of one store. The same phone number in two stores is two separate customers. */
class Customer extends Authenticatable
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'name', 'email', 'phone', 'password', 'status', 'notes'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_order_at' => 'datetime'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('placed_at');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function hasAccount(): bool
    {
        return $this->password !== null;
    }
}
