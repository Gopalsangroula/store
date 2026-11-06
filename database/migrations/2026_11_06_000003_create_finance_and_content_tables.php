<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store finance (sales invoices, credit notes, expenses, double-entry journal),
 * storefront content (pages, banners) and daily report summaries.
 * Issued invoices and posted journal entries are never edited or deleted; mistakes
 * are corrected with credit notes and reversing entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);                       // order | invoice | credit_note | purchase_order
            $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unique(['store_id', 'type', 'year']);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained();
            $t->string('number', 30);
            $t->string('status', 20)->default('issued');  // issued | credited
            $t->dateTime('issued_at');
            $t->string('customer_name');
            $t->string('customer_phone', 30)->nullable();
            $t->string('customer_email')->nullable();
            $t->string('billing_address', 500)->nullable();
            $t->char('currency', 3);
            $t->decimal('subtotal', 19, 4);
            $t->decimal('discount_total', 19, 4)->default(0);
            $t->decimal('shipping_total', 19, 4)->default(0);
            $t->decimal('taxable_amount', 19, 4)->default(0);
            $t->decimal('tax_total', 19, 4)->default(0);
            $t->string('tax_label', 50)->nullable();
            $t->decimal('grand_total', 19, 4);
            $t->timestamps();
            $t->unique(['store_id', 'number']);
            $t->unique('order_id');
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->string('sku', 100)->nullable();
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 19, 4);
            $t->decimal('discount_amount', 19, 4)->default(0);
            $t->decimal('tax_amount', 19, 4)->default(0);
            $t->decimal('line_total', 19, 4);
        });

        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained();
            $t->foreignId('order_id')->constrained();
            $t->string('number', 30);
            $t->decimal('amount', 19, 4);
            $t->decimal('tax_amount', 19, 4)->default(0);
            $t->string('reason', 500);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('issued_at');
            $t->timestamps();
            $t->unique(['store_id', 'number']);
        });

        Schema::create('accounting_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('name');
            $t->string('type', 20); // asset | liability | equity | income | expense
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique(['store_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->date('entry_date');
            $t->string('memo');
            $t->nullableMorphs('reference');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['store_id', 'entry_date']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('accounting_accounts');
            $t->decimal('debit', 19, 4)->default(0);
            $t->decimal('credit', 19, 4)->default(0);
            $t->index(['store_id', 'account_id']);
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('amount', 19, 4);
            $t->string('paid_from', 20)->default('cash');  // cash | bank | khalti
            $t->date('spent_on');
            $t->string('reference')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('slug');
            $t->longText('content')->nullable();
            $t->boolean('in_footer')->default(true);
            $t->boolean('is_published')->default(true);
            $t->timestamps();
            $t->unique(['store_id', 'slug']);
        });

        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('position', 20)->default('feature'); // feature (the two promo cards)
            $t->string('eyebrow')->nullable();
            $t->string('title');
            $t->string('subtitle', 500)->nullable();
            $t->string('image_path')->nullable();
            $t->string('link')->nullable();
            $t->string('button_text', 40)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('daily_sales_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedInteger('orders_count')->default(0);
            $t->unsignedInteger('items_sold')->default(0);
            $t->decimal('gross_sales', 19, 4)->default(0);
            $t->decimal('discounts', 19, 4)->default(0);
            $t->decimal('shipping', 19, 4)->default(0);
            $t->decimal('tax', 19, 4)->default(0);
            $t->decimal('net_sales', 19, 4)->default(0);
            $t->decimal('cost_of_goods', 19, 4)->default(0);
            $t->unsignedInteger('new_customers')->default(0);
            $t->timestamps();
            $t->unique(['store_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['daily_sales_summaries', 'banners', 'pages', 'expenses', 'expense_categories', 'journal_entry_lines',
            'journal_entries', 'accounting_accounts', 'credit_notes', 'invoice_items', 'invoices', 'document_sequences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
