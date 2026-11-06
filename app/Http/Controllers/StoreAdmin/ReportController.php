<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public const REPORTS = ['sales' => 'Sales', 'products' => 'Products', 'customers' => 'Customers', 'inventory' => 'Inventory', 'finance' => 'Tax & profit'];

    public function show(Request $request, ReportService $reports, string $report = 'sales')
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : $to->subDays(29);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $from = $from->max($to->subYears(2)); // keep a single report bounded

        $data = match ($report) {
            'sales' => ['days' => $reports->daily($from, $to), 'methods' => $reports->byPaymentMethod($from, $to), 'statuses' => $reports->byStatus(), 'coupons' => $reports->coupons($from, $to)],
            'products' => ['top' => $reports->topProducts($from, $to, 50)],
            'customers' => $reports->customers($from, $to),
            'inventory' => ['low' => $reports->lowStock(), 'value' => $reports->inventoryValue()],
            'finance' => ['tax' => $reports->tax($from, $to), 'pl' => $reports->profitAndLoss($from, $to)],
        };

        return view('admin.reports', ['report' => $report, 'from' => $from, 'to' => $to, 'data' => $data]);
    }
}
