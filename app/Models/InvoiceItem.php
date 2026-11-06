<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'description', 'sku', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total'];
}
