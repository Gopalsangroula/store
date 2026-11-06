<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Store reports. Day totals are rolled up into daily_sales_summaries (refreshed when an
 * order changes and nightly), so charts over months read a few hundred rows, not every order.
 * "Sales" counts orders that are not cancelled, by the day they were placed.
 */
class ReportService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function refreshDay(\DateTimeInterface $day): DailySalesSummary
    {
        $date = CarbonImmutable::instance($day)->startOfDay();
        $range = [$date, $date->endOfDay()];

        $orders = Order::whereBetween('placed_at', $range)->whereNotIn('status', ['cancelled']);

        $totals = (clone $orders)->selectRaw('COUNT(*) as c, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount_total),0) as disc,
            COALESCE(SUM(shipping_total),0) as ship, COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(grand_total),0) as grand,
            COALESCE(SUM(refunded_total),0) as refunded')->first();

        $items = OrderItem::whereIn('order_id', (clone $orders)->select('id'))
            ->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(COALESCE(unit_cost,0) * quantity),0) as cost')->first();

        return DailySalesSummary::updateOrCreate(
            ['date' => $date->toDateString()],
            [
                'orders_count' => (int) $totals->c,
                'items_sold' => (int) $items->qty,
                'gross_sales' => $totals->gross,
                'discounts' => $totals->disc,
                'shipping' => $totals->ship,
                'tax' => $totals->tax,
                'net_sales' => Money::fromMinor(Money::toMinor($totals->grand) - Money::toMinor($totals->tax) - Money::toMinor($totals->refunded)),
                'cost_of_goods' => $items->cost,
                'new_customers' => Customer::whereBetween('created_at', $range)->count(),
            ]
        );
    }

    /** @return Collection<int, DailySalesSummary> one row per day, gaps filled */
    public function daily(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $existing = DailySalesSummary::whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->keyBy(fn ($row) => $row->date->toDateString());

        $days = collect();

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $key = $day->toDateString();
            // Today (and any day never summarised) is rebuilt live.
            $row = ($day->isToday() || ! $existing->has($key)) ? $this->refreshDay($day) : $existing[$key];
            $days->push($row);
        }

        return $days;
    }

    public function overview(): array
    {
        $today = CarbonImmutable::today();
        $last30 = $this->daily($today->subDays(29), $today);
        $prev30 = DailySalesSummary::whereBetween('date', [$today->subDays(59)->toDateString(), $today->subDays(30)->toDateString()])->sum('net_sales');

        return [
            'today_sales' => (float) $last30->last()->net_sales + (float) $last30->last()->tax,
            'today_orders' => $last30->last()->orders_count,
            'month_sales' => (float) $last30->sum('net_sales'),
            'month_orders' => $last30->sum('orders_count'),
            'prev_month_sales' => (float) $prev30,
            'pending' => Order::where('status', 'pending')->count(),
            'to_verify' => Order::where('payment_status', 'pending_verification')->whereNot('status', 'cancelled')->count(),
            'to_ship' => Order::whereIn('status', ['confirmed', 'processing'])->count(),
            'low_stock' => $this->lowStock()->count(),
            'chart' => $last30->map(fn ($row) => ['date' => $row->date->format('j M'), 'sales' => (float) $row->net_sales, 'orders' => $row->orders_count])->values(),
        ];
    }

    public function lowStock(): Collection
    {
        return Inventory::with('variant.product', 'warehouse')
            ->whereHas('variant', fn ($q) => $q->where('track_inventory', true)->where('is_active', true))
            ->whereRaw('(on_hand - reserved) <= low_stock_threshold')
            ->orderByRaw('(on_hand - reserved)')
            ->limit(50)
            ->get();
    }

    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $this->tenant->id())
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.placed_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('order_items.product_name', 'order_items.variant_name', 'order_items.sku')
            ->selectRaw('order_items.product_name, order_items.variant_name, order_items.sku, SUM(order_items.quantity) as qty,
                SUM(order_items.line_total) as revenue, SUM(COALESCE(order_items.unit_cost,0) * order_items.quantity) as cost')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();
    }

    public function byPaymentMethod(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::whereBetween('placed_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNot('status', 'cancelled')
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(grand_total) as total, SUM(paid_total) as paid')
            ->orderByDesc('total')
            ->get();
    }

    public function byStatus(): Collection
    {
        return Order::groupBy('status')->selectRaw('status, COUNT(*) as orders')->pluck('orders', 'status');
    }

    public function coupons(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('coupon_usages')
            ->join('coupons', 'coupons.id', '=', 'coupon_usages.coupon_id')
            ->join('orders', 'orders.id', '=', 'coupon_usages.order_id')
            ->where('coupon_usages.store_id', $this->tenant->id())
            ->whereBetween('coupon_usages.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('coupons.code', 'coupons.name')
            ->selectRaw('coupons.code, coupons.name, COUNT(*) as uses, SUM(coupon_usages.discount_amount) as discount, SUM(orders.grand_total) as revenue')
            ->orderByDesc('uses')
            ->get();
    }

    public function customers(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $buyers = Order::whereBetween('placed_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNot('status', 'cancelled')->distinct()->pluck('customer_id')->filter();

        $returning = Order::whereIn('customer_id', $buyers)->where('placed_at', '<', $from->startOfDay())
            ->whereNot('status', 'cancelled')->distinct()->count('customer_id');

        return [
            'buyers' => $buyers->count(),
            'returning' => $returning,
            'new' => $buyers->count() - $returning,
            'top' => Customer::orderByDesc('total_spent')->limit(10)->get(),
        ];
    }

    /** VAT collected per month from issued invoices, minus VAT given back on credit notes. */
    public function tax(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $invoices = Invoice::whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("DATE_FORMAT(issued_at, '%Y-%m') as month, COUNT(*) as invoices, SUM(taxable_amount) as taxable, SUM(tax_total) as tax, SUM(grand_total) as total")
            ->groupBy('month')->orderBy('month')->get()->keyBy('month');

        $credits = DB::table('credit_notes')->where('store_id', $this->tenant->id())
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("DATE_FORMAT(issued_at, '%Y-%m') as month, SUM(tax_amount) as tax")
            ->groupBy('month')->pluck('tax', 'month');

        return $invoices->map(function ($row) use ($credits) {
            $row->credited_tax = (float) ($credits[$row->month] ?? 0);
            $row->net_tax = (float) $row->tax - $row->credited_tax;

            return $row;
        })->values();
    }

    /** Profit and loss from the journal: income less expenses for the period. */
    public function profitAndLoss(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entry_lines.store_id', $this->tenant->id())
            ->whereIn('accounting_accounts.type', ['income', 'expense'])
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('accounting_accounts.code', 'accounting_accounts.name', 'accounting_accounts.type')
            ->orderBy('accounting_accounts.code')
            ->selectRaw('accounting_accounts.code, accounting_accounts.name, accounting_accounts.type, SUM(debit) as debit, SUM(credit) as credit')
            ->get()
            ->map(function ($row) {
                $row->amount = $row->type === 'income' ? (float) $row->credit - (float) $row->debit : (float) $row->debit - (float) $row->credit;

                return $row;
            });

        $income = $rows->where('type', 'income')->sum('amount');
        $expenses = $rows->where('type', 'expense')->sum('amount');

        return ['rows' => $rows, 'income' => $income, 'expenses' => $expenses, 'profit' => $income - $expenses];
    }

    public function inventoryValue(): array
    {
        $row = Inventory::join('product_variants', 'product_variants.id', '=', 'inventory.variant_id')
            ->whereNull('product_variants.deleted_at')
            ->selectRaw('SUM(inventory.on_hand) as units, SUM(inventory.on_hand * COALESCE(product_variants.cost_price, 0)) as cost,
                SUM(inventory.on_hand * product_variants.price) as retail')
            ->first();

        return ['units' => (int) $row->units, 'cost' => (float) $row->cost, 'retail' => (float) $row->retail];
    }
}
