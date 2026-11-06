<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers, carts, the discount engine, shipping, tax, currency, orders and payments.
 * Orders keep a full snapshot of what was bought, at what price, currency and rate, so
 * later edits to products, coupons or exchange rates never change a past order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 30);
            $t->string('password')->nullable();      // null = guest checkout, no account yet
            $t->rememberToken();
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('orders_count')->default(0);
            $t->decimal('total_spent', 19, 4)->default(0);
            $t->timestamp('last_order_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'phone']);
            $t->index(['store_id', 'email']);
        });

        Schema::create('customer_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('phone', 30);
            $t->string('province', 60)->nullable();
            $t->string('city', 100);
            $t->string('area', 150)->nullable();
            $t->string('address_line');
            $t->string('landmark')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('token', 64)->unique();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('coupon_code', 50)->nullable();
            $t->string('contact_phone', 30)->nullable();   // filled at checkout, for abandoned-cart follow-up
            $t->timestamp('converted_at')->nullable();
            $t->timestamps();
            $t->index(['store_id', 'updated_at']);
        });

        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->unsignedInteger('quantity');
            $t->timestamps();
            $t->unique(['cart_id', 'variant_id']);
        });

        Schema::create('wishlist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['customer_id', 'product_id']);
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('code', 50);
            $t->string('name');
            $t->string('type', 20);                      // percent | fixed | free_shipping
            $t->decimal('value', 19, 4)->default(0);
            $t->decimal('min_order_amount', 19, 4)->nullable();
            $t->decimal('max_discount_amount', 19, 4)->nullable();
            $t->string('applies_to', 20)->default('all'); // all | products | categories
            $t->boolean('first_order_only')->default(false);
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('usage_limit_per_customer')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['store_id', 'code']);
        });

        Schema::create('coupon_products', function (Blueprint $t) {
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_categories', function (Blueprint $t) {
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->primary(['coupon_id', 'category_id']);
        });

        Schema::create('shipping_zones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('cities')->nullable();             // comma separated; empty = everywhere else
            $t->decimal('rate', 19, 4)->default(0);
            $t->decimal('free_over', 19, 4)->nullable();
            $t->string('estimated_days', 30)->nullable();
            $t->boolean('cod_available')->default(true);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name', 50);                      // "VAT"
            $t->decimal('rate', 8, 4);                   // 13.0000 = 13%
            $t->boolean('is_inclusive')->default(true);  // prices already include the tax
            $t->boolean('applies_to_shipping')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('currencies', function (Blueprint $t) {
            $t->char('code', 3)->primary();
            $t->string('name', 50);
            $t->string('symbol', 10);
            $t->unsignedTinyInteger('decimals')->default(2);
            $t->string('flag', 10)->nullable();
        });

        Schema::create('exchange_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->char('base', 3);
            $t->char('quote', 3);
            $t->decimal('rate', 20, 6);                  // 1 base = rate quote
            $t->dateTime('effective_at');
            $t->timestamps();
            $t->index(['store_id', 'quote', 'effective_at']);
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('number', 30);
            $t->string('access_token', 40);              // lets a guest open their own order page
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // pending | confirmed | processing | shipped | delivered | cancelled | returned
            $t->string('status', 20)->default('pending');
            // unpaid | pending_verification | paid | partially_refunded | refunded | failed
            $t->string('payment_status', 30)->default('unpaid');
            $t->string('payment_method', 30);
            $t->char('currency', 3);
            $t->char('base_currency', 3);
            $t->decimal('exchange_rate', 20, 6)->default(1);
            $t->decimal('subtotal', 19, 4);
            $t->decimal('discount_total', 19, 4)->default(0);
            $t->decimal('shipping_total', 19, 4)->default(0);
            $t->decimal('tax_total', 19, 4)->default(0);
            $t->boolean('tax_inclusive')->default(true);
            $t->decimal('grand_total', 19, 4);
            $t->decimal('paid_total', 19, 4)->default(0);
            $t->decimal('refunded_total', 19, 4)->default(0);
            $t->string('coupon_code', 50)->nullable();
            $t->string('customer_name');
            $t->string('customer_email')->nullable();
            $t->string('customer_phone', 30);
            $t->string('shipping_province', 60)->nullable();
            $t->string('shipping_city', 100);
            $t->string('shipping_area', 150)->nullable();
            $t->string('shipping_address');
            $t->string('shipping_landmark')->nullable();
            $t->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $t->string('shipping_zone_name')->nullable();
            $t->text('customer_note')->nullable();
            $t->string('courier', 60)->nullable();
            $t->string('tracking_number', 100)->nullable();
            $t->boolean('stock_committed')->default(false);
            $t->string('ip', 45)->nullable();
            $t->dateTime('placed_at');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'number']);
            $t->index(['store_id', 'status', 'placed_at']);
            $t->index(['store_id', 'payment_status']);
            $t->index(['store_id', 'customer_phone']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('product_name');
            $t->string('variant_name')->nullable();
            $t->string('sku', 100);
            $t->string('image_path')->nullable();
            $t->decimal('unit_price', 19, 4);
            $t->decimal('unit_cost', 19, 4)->nullable();
            $t->unsignedInteger('quantity');
            $t->decimal('discount_amount', 19, 4)->default(0);
            $t->decimal('tax_amount', 19, 4)->default(0);
            $t->decimal('line_total', 19, 4);
            $t->boolean('is_preorder')->default(false);
            $t->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->string('note', 1000)->nullable();
            $t->boolean('is_customer_visible')->default(true);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('stock_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants');
            $t->foreignId('warehouse_id')->constrained();
            $t->unsignedInteger('quantity');
            $t->string('status', 20)->default('active'); // active | committed | released
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'expires_at']);
        });

        Schema::create('coupon_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_phone', 30);
            $t->decimal('discount_amount', 19, 4);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['coupon_id', 'customer_phone']);
        });

        Schema::create('payment_methods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);                      // cod | khalti | qr | bank
            $t->string('name');
            $t->text('instructions')->nullable();
            $t->text('config')->nullable();              // encrypted JSON (keys, QR image path, bank details)
            $t->boolean('is_enabled')->default(false);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['store_id', 'code']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('method', 30);
            $t->decimal('amount', 19, 4);
            $t->char('currency', 3);
            // initiated | pending_verification | completed | failed | cancelled | refunded
            $t->string('status', 30)->default('initiated');
            $t->string('gateway_reference')->nullable();  // Khalti pidx
            $t->string('transaction_id')->nullable();     // gateway or bank transaction id
            $t->string('payer_reference')->nullable();    // what the customer typed (QR / bank)
            $t->string('proof_path')->nullable();         // screenshot of the QR payment
            $t->json('gateway_response')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['store_id', 'status']);
            $t->unique(['method', 'gateway_reference']);
        });

        Schema::create('payment_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 60);
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 19, 4);
            $t->string('reason', 500)->nullable();
            $t->string('method', 30);                     // how the money went back
            $t->string('reference')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payment_refunds', 'payment_logs', 'payments', 'payment_methods', 'coupon_usages', 'stock_reservations',
            'order_status_histories', 'order_items', 'orders','exchange_rates', 'currencies', 'tax_rates',
            'shipping_zones', 'coupon_categories', 'coupon_products', 'coupons', 'wishlist_items', 'cart_items', 'carts',
            'customer_addresses', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
