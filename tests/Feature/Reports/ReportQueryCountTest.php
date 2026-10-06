<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(User $user): array
    {
        $category = Category::create(['name' => 'Fixture Category']);

        $products = [];
        for ($i = 1; $i <= 3; $i++) {
            $products[] = Product::create([
                'name' => "Product $i",
                'category_id' => $category->id,
                'price' => "{$i}0.00",
                'stock' => 10 + $i,
            ]);
        }

        $customer = Customer::create(['name' => 'Fixture Customer', 'phone' => '0700000000']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => 'AFN',
        ]);
        $subtotal = '0.00';
        foreach ($products as $product) {
            $lineTotal = bcmul((string) $product->price, '3', 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_price' => $product->price,
                'subtotal' => $lineTotal,
            ]);
        }
        $order->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);
        $return = OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-09',
            'total_amount' => '0.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);
        $returnSubtotal = '0.00';
        foreach ($products as $product) {
            $lineTotal = bcmul((string) $product->price, '1', 2);
            $returnSubtotal = bcadd($returnSubtotal, $lineTotal, 2);
            OrderReturnItem::create([
                'order_return_id' => $return->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'subtotal' => $lineTotal,
            ]);
        }
        $return->update(['total_amount' => $returnSubtotal]);

        $supplier = Supplier::create(['name' => 'Fixture Supplier', 'phone' => '0700000001']);
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => 'AFN',
        ]);
        $purchaseSubtotal = '0.00';
        foreach ($products as $product) {
            $lineTotal = bcmul((string) $product->price, '5', 2);
            $purchaseSubtotal = bcadd($purchaseSubtotal, $lineTotal, 2);
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_price' => $product->price,
                'subtotal' => $lineTotal,
            ]);
        }
        $purchase->update(['subtotal' => $purchaseSubtotal, 'total_amount' => $purchaseSubtotal]);
        $purchaseReturn = PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'return_date' => '2026-08-10',
            'total_amount' => '0.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);
        $returnSubtotal2 = '0.00';
        foreach ($products as $product) {
            $lineTotal = bcmul((string) $product->price, '2', 2);
            $returnSubtotal2 = bcadd($returnSubtotal2, $lineTotal, 2);
            PurchaseReturnItem::create([
                'purchase_return_id' => $purchaseReturn->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => $product->price,
                'subtotal' => $lineTotal,
            ]);
        }
        $purchaseReturn->update(['total_amount' => $returnSubtotal2]);

        return ['products' => $products];
    }

    public function test_balance_sheet_makes_bounded_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seedFixture($user);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        // Settings are read through a per-process memo: warm it before both
        // measurements so run two isn't "faster" for reasons unrelated to
        // product count.
        Setting::flushMemo();

        $response = $this->get('/reports/balance-sheet?currency=AFN');

        $response->assertOk();
        $queryCount = count($queries);

        // With 3 products, 1 order, 1 purchase: legacy N+1 was ~36 queries; the optimized version
        // should stay flat because aggregations are grouped per single query.
        $this->assertLessThanOrEqual(32, $queryCount, "balance_sheet made $queryCount queries. First few queries:\n".implode("\n", array_slice($queries, 0, 5)));

        // Seed more products, verify the query count does NOT grow with product count.
        for ($i = 4; $i <= 12; $i++) {
            Product::create([
                'name' => "Extra $i",
                'category_id' => Category::first()->id,
                'price' => '5.00',
                'stock' => 1,
            ]);
        }

        $queries2 = [];
        DB::listen(function (QueryExecuted $query) use (&$queries2) {
            $queries2[] = $query->sql;
        });

        Setting::flushMemo();

        $response2 = $this->get('/reports/balance-sheet?currency=AFN');
        $response2->assertOk();
        $queryCount2 = count($queries2);

        $this->assertEquals($queryCount, $queryCount2, "Query count grew from $queryCount to $queryCount2 when products increased. balance_sheet is meant to be O(1) in product count.");
    }

    public function test_stock_report_makes_bounded_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seedFixture($user);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        Setting::flushMemo();

        $response = $this->get('/reports/stock?currency=AFN');

        $response->assertOk();
        $queryCount = count($queries);

        // With 3 products + relations, the legacy N+1 made ~2 per product per direction (so ~6+ queries
        // just for the price averages). The grouped version should collapse those to 2 total.
        // Allow a slightly looser bound but assert O(1): adding more products must not grow the count.
        $this->assertLessThan(30, $queryCount, "stock_report made $queryCount queries. First few:\n".implode("\n", array_slice($queries, 0, 5)));

        for ($i = 4; $i <= 12; $i++) {
            Product::create([
                'name' => "Extra $i",
                'category_id' => Category::first()->id,
                'price' => '5.00',
                'stock' => 1,
            ]);
        }

        $queries2 = [];
        DB::listen(function (QueryExecuted $query) use (&$queries2) {
            $queries2[] = $query->sql;
        });

        Setting::flushMemo();

        $response2 = $this->get('/reports/stock?currency=AFN');
        $response2->assertOk();
        $queryCount2 = count($queries2);

        $this->assertEquals($queryCount, $queryCount2, "Query count grew from $queryCount to $queryCount2 when products increased. stock_report is meant to be O(1) in product count.");
    }
}
