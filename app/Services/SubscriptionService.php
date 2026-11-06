<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform billing: what each client store pays the platform. This is a separate ledger
 * from any store's own sales and never touches store invoices or journals.
 */
class SubscriptionService
{
    public function __construct(private DocumentNumber $numbers)
    {
    }

    public function subscribe(Store $store, Plan $plan, string $cycle = 'monthly', int $trialDays = 0): StoreSubscription
    {
        return DB::transaction(function () use ($store, $plan, $cycle, $trialDays) {
            StoreSubscription::where('store_id', $store->id)->where('status', 'active')->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $start = now()->startOfDay();
            $end = $trialDays > 0 ? $start->copy()->addDays($trialDays) : $this->periodEnd($start, $cycle);
            $price = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

            $subscription = StoreSubscription::create([
                'store_id' => $store->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'price' => $price,
                'status' => 'active',
                'starts_at' => $start,
                'ends_at' => $end,
            ]);

            $store->update(['plan_id' => $plan->id]);

            if ($trialDays === 0 && Money::toMinor($price) + Money::toMinor($plan->setup_fee) > 0) {
                $this->invoice($subscription, $start, $end, Money::toMinor($plan->setup_fee));
            }

            return $subscription;
        });
    }

    /** Raise the next period's invoice for subscriptions ending within $days. */
    public function generateRenewals(int $days = 7): int
    {
        $count = 0;

        $due = StoreSubscription::with('store', 'plan')
            ->where('status', 'active')
            ->where('ends_at', '<=', now()->addDays($days))
            ->get();

        foreach ($due as $subscription) {
            $start = $subscription->ends_at->copy();
            $exists = SubscriptionInvoice::where('store_subscription_id', $subscription->id)
                ->whereDate('period_start', $start->toDateString())
                ->exists();

            if (! $exists && Money::toMinor($subscription->price) > 0) {
                $this->invoice($subscription, $start, $this->periodEnd($start, $subscription->billing_cycle));
                $count++;
            }
        }

        return $count;
    }

    public function recordPayment(SubscriptionInvoice $invoice, float $amount, string $method, ?string $reference, ?User $user): SubscriptionPayment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $user) {
            $invoice = SubscriptionInvoice::whereKey($invoice->id)->lockForUpdate()->first();

            $payment = SubscriptionPayment::create([
                'subscription_invoice_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => now(),
                'recorded_by' => $user?->id,
            ]);

            $paid = Money::toMinor($invoice->paid_amount) + Money::toMinor($amount);
            $fullyPaid = $paid >= Money::toMinor($invoice->amount);

            $invoice->update([
                'paid_amount' => Money::fromMinor($paid),
                'status' => $fullyPaid ? 'paid' : 'unpaid',
                'paid_at' => $fullyPaid ? now() : null,
            ]);

            if ($fullyPaid && $invoice->subscription) {
                $subscription = $invoice->subscription;

                // A paid renewal extends the subscription to the end of the invoiced period.
                if ($invoice->period_end && $invoice->period_end->gt($subscription->ends_at)) {
                    $subscription->update(['ends_at' => $invoice->period_end->copy()->endOfDay(), 'status' => 'active']);
                }

                $store = $invoice->store;

                if ($store->status === 'suspended' && $store->suspended_reason === 'Subscription expired') {
                    $store->update(['status' => 'active', 'suspended_reason' => null]);
                }
            }

            ActivityLog::record('subscription.payment', $invoice, ['amount' => $amount, 'method' => $method]);

            return $payment;
        });
    }

    /** Expire subscriptions past their end and suspend stores past the grace period. */
    public function enforce(): int
    {
        $suspended = 0;
        $grace = (int) config('platform.grace_days');

        foreach (StoreSubscription::with('store')->where('status', 'active')->where('ends_at', '<', now())->get() as $subscription) {
            $store = $subscription->store;

            if (! $store) {
                continue;
            }

            if ($subscription->ends_at->copy()->addDays($grace)->isPast()) {
                $subscription->update(['status' => 'expired']);

                if ($store->status === 'active') {
                    $store->update(['status' => 'suspended', 'suspended_reason' => 'Subscription expired']);
                    $suspended++;
                }
            }
        }

        return $suspended;
    }

    private function invoice(StoreSubscription $subscription, Carbon $start, Carbon $end, int $setupFeeMinor = 0): SubscriptionInvoice
    {
        $plan = $subscription->plan;
        $amount = Money::toMinor($subscription->price) + $setupFeeMinor;

        return SubscriptionInvoice::create([
            'store_id' => $subscription->store_id,
            'store_subscription_id' => $subscription->id,
            'number' => $this->numbers->nextPlatform('subscription', 'SUB'),
            'description' => $plan->name.' plan ('.$subscription->billing_cycle.') '.$start->format('j M Y').' to '.$end->format('j M Y')
                .($setupFeeMinor > 0 ? ' + setup fee' : ''),
            'amount' => Money::fromMinor($amount),
            'status' => 'unpaid',
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'due_on' => $start->toDateString(),
        ]);
    }

    private function periodEnd(Carbon $start, string $cycle): Carbon
    {
        return $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
    }
}
