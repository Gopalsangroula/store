<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'name', 'cities', 'rate', 'free_over', 'estimated_days', 'cod_available', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['cod_available' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return list<string> lower-case city names; empty means "everywhere else" */
    public function cityList(): array
    {
        return array_values(array_filter(array_map(
            fn ($city) => mb_strtolower(trim($city)),
            explode(',', (string) $this->cities)
        )));
    }

    public function covers(string $city): bool
    {
        $list = $this->cityList();

        return $list === [] || in_array(mb_strtolower(trim($city)), $list, true);
    }
}
