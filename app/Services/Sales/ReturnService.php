<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class ReturnService
{
    public function createReturn(
        string $direction,
        Model $parent,
        array $items,
        string $returnDate,
        ?string $reason = null,
        string $status = 'completed',
    ): OrderReturn {
        if ($direction !== 'order') {
            throw new LogicException('ReturnService::createReturn currently supports only the order direction. Supplier returns are a follow-up.');
        }
        if (! $parent instanceof Order) {
            throw new LogicException('ReturnService::createReturn for direction=order requires an Order parent.');
        }

        return DB::transaction(function () use ($parent, $items, $returnDate, $reason, $status) {
            $this->validateReturnableQuantities($parent, $items);

            $return = OrderReturn::create([
                'user_id' => Auth::id(),
                'order_id' => $parent->id,
                'customer_id' => $parent->customer_id,
                'return_date' => $returnDate,
                'reason' => $reason,
                'total_amount' => '0.00',
                'status' => $status,
                'currency' => $parent->currency,
            ]);

            $total = '0.00';
            $returnItems = [];

            foreach ($items as $item) {
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                $lineTotal = bcmul((string) $item['unit_price'], (string) $item['quantity'], 2);
                $total = bcadd($total, $lineTotal, 2);

                $returnItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $lineTotal,
                ];

                $product->increment('stock', $item['quantity']);
            }

            $return->items()->createMany($returnItems);
            $return->update(['total_amount' => $total]);

            foreach ($returnItems as $item) {
                StockMovement::create([
                    'user_id' => Auth::id(),
                    'product_id' => $item['product_id'],
                    'quantity_change' => $item['quantity'],
                    'movement_type' => 'return',
                    'reference_type' => 'order_return',
                    'reference_id' => $return->id,
                    'notes' => __('messages.return'),
                ]);
            }

            return $return;
        });
    }

    protected function validateReturnableQuantities(Order $order, array $items): void
    {
        $soldQuantities = $order->orderItems()
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();

        $alreadyReturned = $this->returnedQuantities($order);

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $requested = (int) $item['quantity'];

            $sold = $soldQuantities[$productId] ?? 0;
            $returned = $alreadyReturned[$productId] ?? 0;
            $returnable = max(0, $sold - $returned);

            if ($requested > $returnable) {
                throw ValidationException::withMessages([
                    'products' => __('messages.return_exceeds_returnable', [
                        'product' => Product::find($productId)?->name ?? "#{$productId}",
                        'returnable' => $returnable,
                    ]),
                ]);
            }
        }
    }

    protected function returnedQuantities(Order $order): array
    {
        $returnIds = OrderReturn::where('order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->pluck('id');

        if ($returnIds->isEmpty()) {
            return [];
        }

        return DB::table('order_return_items')
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->whereIn('order_return_id', $returnIds)
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }
}
