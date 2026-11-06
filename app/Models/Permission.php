<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'group'];

    /** Every permission a store role can hold, grouped as on the admin menu. */
    public const ALL = [
        'Catalog' => [
            'products.view' => 'View products',
            'products.manage' => 'Create and edit products, categories and brands',
            'reviews.manage' => 'Approve reviews',
        ],
        'Inventory' => [
            'inventory.view' => 'View stock',
            'inventory.manage' => 'Adjust and transfer stock',
            'purchases.manage' => 'Suppliers and purchase orders',
        ],
        'Sales' => [
            'orders.view' => 'View orders',
            'orders.manage' => 'Update orders and shipping',
            'payments.verify' => 'Verify QR and bank payments',
            'refunds.manage' => 'Refunds and credit notes',
            'customers.view' => 'View customers',
        ],
        'Marketing' => [
            'coupons.manage' => 'Coupons and discounts',
            'content.manage' => 'Banners and pages',
        ],
        'Finance' => [
            'finance.view' => 'Invoices, accounts and reports',
            'expenses.manage' => 'Record expenses',
        ],
        'Settings' => [
            'settings.manage' => 'Store settings, payments, shipping and taxes',
            'staff.manage' => 'Staff and roles',
        ],
    ];
}
