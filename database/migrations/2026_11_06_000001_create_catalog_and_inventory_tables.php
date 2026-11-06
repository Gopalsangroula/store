<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog (categories, brands, products, variants, images, reviews) and the
 * inventory ledger. Stock is never a column on products: `inventory` holds the
 * current figures per variant and warehouse, and every change is written to
 * `inventory_transactions`, which is append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->string('image_path')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['store_id', 'slug']);
        });

        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('logo_path')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('badge', 60)->nullable();          // e.g. "Best seller", "Limited time"
            $t->string('short_description', 500)->nullable();
            $t->longText('description')->nullable();
            $t->string('status', 20)->default('draft');    // draft | active | archived
            $t->boolean('is_featured')->default(false);
            $t->boolean('is_taxable')->default(true);
            $t->string('seo_title')->nullable();
            $t->string('seo_description', 500)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['store_id', 'slug']);
            $t->index(['store_id', 'status', 'sort']);
        });

        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('alt')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name')->nullable();                // "Black", "64 GB / Blue"; null for a single-variant product
            $t->string('sku', 100);
            $t->decimal('price', 19, 4);
            $t->decimal('compare_at_price', 19, 4)->nullable();
            $t->decimal('cost_price', 19, 4)->nullable();
            $t->string('image_path')->nullable();
            $t->string('swatch', 20)->nullable();          // colour hex for the variant chip
            $t->boolean('track_inventory')->default(true);
            $t->boolean('allow_preorder')->default(false); // can be bought when out of stock
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['store_id', 'sku']);
        });

        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->string('name');
            $t->unsignedTinyInteger('rating');
            $t->text('body')->nullable();
            $t->boolean('is_approved')->default(false);
            $t->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('code', 20);
            $t->string('address')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['store_id', 'code']);
        });

        Schema::create('inventory', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->integer('on_hand')->default(0);
            $t->integer('reserved')->default(0);
            $t->unsignedInteger('low_stock_threshold')->default(5);
            $t->timestamps();
            $t->unique(['warehouse_id', 'variant_id']);
            $t->index(['store_id', 'variant_id']);
        });

        Schema::create('inventory_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained();
            $t->foreignId('variant_id')->constrained('product_variants');
            // purchase_receipt | sale | adjustment | transfer_in | transfer_out | return | cancellation
            $t->string('type', 30);
            $t->integer('quantity');                       // signed: + in, - out
            $t->integer('balance_after');
            $t->decimal('unit_cost', 19, 4)->nullable();
            $t->nullableMorphs('reference');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['store_id', 'variant_id', 'created_at']);
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->string('pan_vat_number', 30)->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained();
            $t->string('number', 30);
            $t->string('status', 20)->default('draft'); // draft | ordered | received | cancelled
            $t->decimal('total', 19, 4)->default(0);
            $t->text('note')->nullable();
            $t->date('ordered_on')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['store_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('received_quantity')->default(0);
            $t->decimal('unit_cost', 19, 4);
        });
    }

    public function down(): void
    {
        foreach (['purchase_order_items', 'purchase_orders', 'suppliers', 'inventory_transactions', 'inventory',
            'warehouses', 'product_reviews', 'product_variants', 'product_images', 'products', 'brands', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
