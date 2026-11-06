<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockReservation;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only code that changes stock. Every change locks the inventory rows it touches
 * and writes a ledger row, so concurrent orders cannot oversell and every unit can be
 * traced.  Available = on_hand - reserved.
 *
 *   order placed     -> reserve      (reserved +)
 *   paid / confirmed -> commit       (on_hand -, reserved -, ledger "sale")
 *   cancelled        -> release      (reserved -) or, if already committed, restock
 *   return inspected -> restockReturn (on_hand +, ledger "return")
 */
class InventoryService
{
    /**
     * Reserve stock for each line of a new order. Lines that cannot be covered either
     * become pre-orders (when the variant allows it) or stop the whole checkout.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $lines  keyed by order item id
     * @return array<int, bool> order item id => is pre-order
     */
    public function reserveForOrder(Order $order, array $lines, ?\DateTimeInterface $expiresAt): array
    {
        $preorders = [];

        // Lock in a fixed order (variant id) so two checkouts never wait on each other in a circle.
        uasort($lines, fn ($a, $b) => $a['variant']->id <=> $b['variant']->id);

        foreach ($lines as $itemId => $line) {
            $variant = $line['variant'];
            $quantity = $line['quantity'];

            if (! $variant->track_inventory) {
                $preorders[$itemId] = false;

                continue;
            }

            $rows = Inventory::where('variant_id', $variant->id)
                ->join('warehouses', 'warehouses.id', '=', 'inventory.warehouse_id')
                ->where('warehouses.is_active', true)
                ->orderByDesc('warehouses.is_default')
                ->orderBy('inventory.id')
                ->select('inventory.*')
                ->lockForUpdate()
                ->get();

            $available = (int) $rows->sum(fn (Inventory $row) => $row->available());

            if ($available < $quantity) {
                if (! $variant->allow_preorder) {
                    throw OutOfStockException::for($variant->label(), $available);
                }

                $preorders[$itemId] = true; // nothing reserved; fulfilled when stock arrives

                continue;
            }

            $remaining = $quantity;

            foreach ($rows as $row) {
                $take = min($remaining, $row->available());

                if ($take <= 0) {
                    continue;
                }

                $row->increment('reserved', $take);

                StockReservation::create([
                    'store_id' => $order->store_id,
                    'order_id' => $order->id,
                    'variant_id' => $variant->id,
                    'warehouse_id' => $row->warehouse_id,
                    'quantity' => $take,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                ]);

                $remaining -= $take;

                if ($remaining === 0) {
                    break;
                }
            }

            $preorders[$itemId] = false;
        }

        return $preorders;
    }

    /** Turn an order's reservations into real stock deductions (on payment or confirmation). */
    public function commit(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($order->stock_committed) {
                return;
            }

            foreach ($order->reservations()->where('status', 'active')->orderBy('variant_id')->get() as $reservation) {
                $row = $this->lockedRow($reservation->warehouse_id, $reservation->variant_id);
                $row->reserved = max(0, $row->reserved - $reservation->quantity);
                $row->on_hand -= $reservation->quantity;
                $row->save();

                $this->ledger($row, 'sale', -$reservation->quantity, $order, 'Order '.$order->number);
                $reservation->update(['status' => 'committed', 'expires_at' => null]);
            }

            $order->update(['stock_committed' => true]);
        });
    }

    /** Give back what an order held. Committed stock is put back on the shelf. */
    public function release(Order $order, string $reason = 'Order cancelled'): void
    {
        DB::transaction(function () use ($order, $reason) {
            foreach ($order->reservations()->whereIn('status', ['active', 'committed'])->orderBy('variant_id')->get() as $reservation) {
                $row = $this->lockedRow($reservation->warehouse_id, $reservation->variant_id);

                if ($reservation->status === 'active') {
                    $row->reserved = max(0, $row->reserved - $reservation->quantity);
                    $row->save();
                } else {
                    $row->on_hand += $reservation->quantity;
                    $row->save();
                    $this->ledger($row, 'cancellation', $reservation->quantity, $order, $reason.' '.$order->number);
                }

                $reservation->update(['status' => 'released']);
            }

            $order->update(['stock_committed' => false]);
        });
    }

    /** Returned goods go back into sellable stock only after someone has inspected them. */
    public function restockReturn(Order $order, array $quantities, ?int $warehouseId = null): void
    {
        $warehouseId ??= $this->defaultWarehouse()->id;

        DB::transaction(function () use ($order, $quantities, $warehouseId) {
            foreach ($order->items as $item) {
                $quantity = min((int) ($quantities[$item->id] ?? 0), $item->quantity);

                if ($quantity <= 0 || ! $item->variant_id) {
                    continue;
                }

                $row = $this->lockedRow($warehouseId, $item->variant_id);
                $row->on_hand += $quantity;
                $row->save();
                $this->ledger($row, 'return', $quantity, $order, 'Return from '.$order->number);
            }
        });
    }

    /** Manual correction (damage, count difference, opening stock). $delta may be negative. */
    public function adjust(ProductVariant $variant, Warehouse $warehouse, int $delta, string $note, ?float $unitCost = null, ?Model $reference = null, string $type = 'adjustment'): Inventory
    {
        return DB::transaction(function () use ($variant, $warehouse, $delta, $note, $unitCost, $reference, $type) {
            $row = $this->lockedRow($warehouse->id, $variant->id);

            if ($row->on_hand + $delta < $row->reserved) {
                throw new \RuntimeException('That would leave less stock than open orders have reserved ('.$row->reserved.').');
            }

            $row->on_hand += $delta;
            $row->save();
            $this->ledger($row, $type, $delta, $reference, $note, $unitCost);

            return $row;
        });
    }

    public function transfer(ProductVariant $variant, Warehouse $from, Warehouse $to, int $quantity, string $note = ''): void
    {
        if ($from->is($to) || $quantity <= 0) {
            throw new \InvalidArgumentException('Choose two different warehouses and a quantity above zero.');
        }

        DB::transaction(function () use ($variant, $from, $to, $quantity, $note) {
            // Lock both rows in id order.
            [$first, $second] = $from->id < $to->id ? [$from, $to] : [$to, $from];
            $rows = [
                $first->id => $this->lockedRow($first->id, $variant->id),
                $second->id => $this->lockedRow($second->id, $variant->id),
            ];

            $source = $rows[$from->id];

            if ($source->available() < $quantity) {
                throw new \RuntimeException("Only {$source->available()} available in {$from->name}.");
            }

            $source->on_hand -= $quantity;
            $source->save();
            $this->ledger($source, 'transfer_out', -$quantity, null, trim("To {$to->name}. {$note}"));

            $target = $rows[$to->id];
            $target->on_hand += $quantity;
            $target->save();
            $this->ledger($target, 'transfer_in', $quantity, null, trim("From {$from->name}. {$note}"));
        });
    }

    /** Receive a purchase order: stock goes up and the last cost price is updated. */
    public function receivePurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->first();

            if ($purchaseOrder->status === 'received') {
                return;
            }

            foreach ($purchaseOrder->items as $item) {
                $outstanding = $item->quantity - $item->received_quantity;

                if ($outstanding <= 0) {
                    continue;
                }

                $row = $this->lockedRow($purchaseOrder->warehouse_id, $item->variant_id);
                $row->on_hand += $outstanding;
                $row->save();
                $this->ledger($row, 'purchase_receipt', $outstanding, $purchaseOrder, 'PO '.$purchaseOrder->number, (float) $item->unit_cost);

                $item->update(['received_quantity' => $item->quantity]);
                ProductVariant::whereKey($item->variant_id)->update(['cost_price' => $item->unit_cost]);
            }

            $purchaseOrder->update(['status' => 'received', 'received_at' => now()]);
        });
    }

    /** Release reservations of unpaid online orders whose payment window has passed. */
    public function releaseExpired(): int
    {
        $count = 0;

        $orderIds = StockReservation::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->distinct()
            ->pluck('order_id');

        foreach (Order::withoutGlobalScopes()->whereIn('id', $orderIds)->get() as $order) {
            if (in_array($order->payment_status, ['paid', 'pending_verification'], true)) {
                continue;
            }

            app(\App\Tenancy\TenantContext::class)->run($order->store, function () use ($order, &$count) {
                app(OrderService::class)->cancel($order, 'Payment was not completed in time, so the items were released.', null, false);
                $count++;
            });
        }

        return $count;
    }

    public function defaultWarehouse(): Warehouse
    {
        return Warehouse::where('is_default', true)->first()
            ?? Warehouse::orderBy('id')->firstOrFail();
    }

    private function lockedRow(int $warehouseId, int $variantId): Inventory
    {
        Inventory::firstOrCreate(['warehouse_id' => $warehouseId, 'variant_id' => $variantId]);

        return Inventory::where('warehouse_id', $warehouseId)->where('variant_id', $variantId)->lockForUpdate()->first();
    }

    private function ledger(Inventory $row, string $type, int $quantity, ?Model $reference, ?string $note, ?float $unitCost = null): void
    {
        InventoryTransaction::create([
            'store_id' => $row->store_id,
            'warehouse_id' => $row->warehouse_id,
            'variant_id' => $row->variant_id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $row->on_hand,
            'unit_cost' => $unitCost,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note ? mb_substr($note, 0, 250) : null,
            'user_id' => auth('web')->id(),
        ]);
    }
}
