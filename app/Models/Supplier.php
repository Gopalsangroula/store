<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'name', 'contact_person', 'phone', 'email', 'address', 'pan_vat_number'];
}
