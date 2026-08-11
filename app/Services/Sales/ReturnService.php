<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
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
    ): Model {
        $spec = $this->specFor($direction, $parent);

        return DB::transaction(function () use ($spec, $parent, $items, $returnDate, $reason, $status) {
            $this->validateReturnableQuantities($spec, $parent, $items);

            $return = $spec['return_model']::create([
                'user_id' => Auth::id(),
                $spec['parent_fk'] => $parent->id,
                $spec['party_fk'] => $parent->person_id,
                'return_date' => $returnDate,
                'reason' => $reason,
                'total_amount' => '0.00',
                'status' => $status,
                'currency' => $parent->currency,
            ]);

            $total = '0.00';
            $returnItems = [];
            $delta = $spec['stock_delta'];

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

                if ($delta > 0) {
                    $product->increment('stock', $item['quantity']);
                } else {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            $return->items()->createMany($returnItems);
            $return->update(['total_amount' => $total]);

            foreach ($returnItems as $item) {
                StockMovement::create([
                    'user_id' => Auth::id(),
                    'product_id' => $item['product_id'],
                    'quantity_change' => $delta * $item['quantity'],
                    'movement_type' => $spec['movement_type'],
                    'reference_type' => $spec['reference_type'],
                    'reference_id' => $return->id,
                    'notes' => __($spec['translation_key']),
                ]);
            }

            return $return;
        });
    }

    private function specFor(string $direction, Model $parent): array
    {
        return match ($direction) {
            'order' => $parent instanceof Order ? [
                'return_model' => OrderReturn::class,
                'parent_fk' => 'order_id',
                'party_fk' => 'customer_id',
                'sold_relation' => 'orderItems',
                'return_fk' => 'order_id',
                'return_item_table' => 'order_return_items',
                'return_item_fk' => 'order_return_id',
                'stock_delta' => +1,
                'movement_type' => 'return',
                'reference_type' => 'order_return',
                'translation_key' => 'messages.return',
            ] : throw new LogicException('CreateReturn of order direction requires an Order parent.'),
            'purchase' => $parent instanceof Purchase ? [
                'return_model' => PurchaseReturn::class,
                'parent_fk' => 'purchase_id',
                'party_fk' => 'supplier_id',
                'sold_relation' => 'purchaseItems',
                'return_fk' => 'purchase_id',
                'return_item_table' => 'purchase_return_items',
                'return_item_fk' => 'purchase_return_id',
                'stock_delta' => -1,
                'movement_type' => 'purchase_return',
                'reference_type' => 'purchase_return',
                'translation_key' => 'messages.purchase_return',
            ] : throw new LogicException('CreateReturn of purchase direction requires a Purchase parent.'),
            default => throw new LogicException("Unknown direction '{$direction}'. Expected 'order' or 'purchase'."),
        };
    }

    protected function validateReturnableQuantities(array $spec, Model $parent, array $items): void
    {
        $soldQuantities = $parent->{$spec['sold_relation']}()
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();

        $alreadyReturned = $this->returnedQuantities($spec, $parent);

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

    protected function returnedQuantities(array $spec, Model $parent): array
    {
        $returnIds = $spec['return_model']::where($spec['return_fk'], $parent->id)
            ->where('status', '!=', 'cancelled')
            ->pluck('id');

        if ($returnIds->isEmpty()) {
            return [];
        }

        return DB::table($spec['return_item_table'])
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->whereIn($spec['return_item_fk'], $returnIds)
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    public function revertReturn(Model $return): void
    {
        $direction = $return instanceof OrderReturn ? 'order' : ($return instanceof PurchaseReturn ? 'purchase' : null);

        if ($direction === null) {
            throw new LogicException('ReturnService::revertReturn only accepts OrderReturn or PurchaseReturn.');
        }

        if ($return->status === 'cancelled') {
            return;
        }

        $spec = $this->specForCancel($direction);

        DB::transaction(function () use ($return, $spec) {
            foreach ($return->items as $item) {
                $product = Product::where('id', $item->product_id)->lockForUpdate()->firstOrFail();

                if ($spec['stock_delta'] > 0) {
                    $product->decrement('stock', $item->quantity);
                } else {
                    $product->increment('stock', $item->quantity);
                }

                StockMovement::create([
                    'user_id' => Auth::id(),
                    'product_id' => $item->product_id,
                    'quantity_change' => -$spec['stock_delta'] * $item->quantity,
                    'movement_type' => $spec['cancelled_movement_type'],
                    'reference_type' => $spec['reference_type'],
                    'reference_id' => $return->id,
                    'notes' => __($spec['cancelled_translation_key']),
                ]);
            }

            $return->update(['status' => 'cancelled']);
        });
    }

    private function specForCancel(string $direction): array
    {
        return [
            'stock_delta' => $direction === 'order' ? +1 : -1,
            'cancelled_movement_type' => $direction === 'order' ? 'return_cancelled' : 'purchase_return_cancelled',
            'reference_type' => $direction === 'order' ? 'order_return' : 'purchase_return',
            'cancelled_translation_key' => $direction === 'order' ? 'messages.return_cancelled' : 'messages.purchase_return_cancelled',
        ];
    }
}
