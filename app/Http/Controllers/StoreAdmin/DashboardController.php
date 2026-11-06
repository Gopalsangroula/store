<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReportService;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function index(ReportService $reports)
    {
        $today = CarbonImmutable::today();

        return view('admin.dashboard', [
            'stats' => $reports->overview(),
            'recent' => Order::latest('placed_at')->limit(8)->get(),
            'toVerify' => Order::where('payment_status', 'pending_verification')->whereNot('status', 'cancelled')->latest('placed_at')->limit(5)->get(),
            'lowStock' => $reports->lowStock()->take(6),
            'top' => $reports->topProducts($today->subDays(29), $today, 5),
        ]);
    }
}
