<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(User $user, array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Fixture Category']);

        return Product::create(array_merge([
            'name' => 'Fixture Product',
            'category_id' => $category->id,
            'price' => '0.00',
            'stock' => 10,
        ], $overrides));
    }

    private function makePurchase(User $user, Supplier $supplier, array $overrides = []): Purchase
    {
        return Purchase::create(array_merge([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => 'AFN',
        ], $overrides));
    }

    private function addPurchaseItem(Purchase $purchase, Product $product, int $quantity, string $unitPrice): void
    {
        $lineTotal = bcmul((string) $quantity, $unitPrice, 2);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $lineTotal,
        ]);
        $purchase->update([
            'subtotal' => bcadd((string) $purchase->subtotal, $lineTotal, 2),
            'total_amount' => bcadd((string) $purchase->total_amount, $lineTotal, 2),
        ]);
    }

    private function addOrderItem(Order $order, Product $product, int $quantity, string $unitPrice): void
    {
        $lineTotal = bcmul((string) $quantity, $unitPrice, 2);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $lineTotal,
        ]);
        $order->update([
            'subtotal' => bcadd((string) $order->subtotal, $lineTotal, 2),
            'total_amount' => bcadd((string) $order->total_amount, $lineTotal, 2),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/reports/stock')->assertRedirect(route('login'));
    }

    public function test_page_renders_currency_filter_and_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeProduct($user, ['name' => 'Visible Product']);

        $response = $this->get('/reports/stock');

        $response->assertOk();
        $response->assertSee('Visible Product');
        $response->assertSee(__('messages.afn'));
        $response->assertSee(__('messages.usd'));
        $response->assertSee('0.00');
    }

    public function test_average_purchase_price_is_quantity_weighted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Weighted Product', 'stock' => 10]);
        $supplier = Supplier::create(['name' => 'Weighted Supplier', 'phone' => '0700000001']);

        $this->addPurchaseItem($this->makePurchase($user, $supplier), $product, 1, '100.00');
        $this->addPurchaseItem($this->makePurchase($user, $supplier), $product, 100, '10.00');

        $response = $this->get('/reports/stock');

        $response->assertOk();
        // Weighted average = (1×100 + 100×10) / 101 = 1100/101 = 10.89.
        // A plain AVG would have produced 55.00 and inflated the stock value to 550.00.
        $response->assertSee('10.89');
        $response->assertSee('108.90');
        $response->assertDontSee('550.00');
    }

    public function test_currency_filter_keeps_average_prices_separate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Stock held per pool: 10 units from the AFN purchases and 10 from the
        // USD purchases — each currency view values its own pool.
        $product = $this->makeProduct($user, ['name' => 'Dual Currency Product', 'stock_afn' => 10, 'stock_usd' => 10]);
        $supplier = Supplier::create(['name' => 'Dual Currency Supplier', 'phone' => '0700000002']);

        $this->addPurchaseItem($this->makePurchase($user, $supplier, ['currency' => 'AFN']), $product, 1, '100.00');
        $this->addPurchaseItem($this->makePurchase($user, $supplier, ['currency' => 'USD']), $product, 2, '5.00');

        $afnResponse = $this->get('/reports/stock');
        $afnResponse->assertOk();
        $afnResponse->assertSee('100.00');
        $afnResponse->assertSee('1,000.00');
        $afnResponse->assertDontSee('5.00');

        $usdResponse = $this->get('/reports/stock?currency=USD');
        $usdResponse->assertOk();
        $usdResponse->assertSee('5.00');
        $usdResponse->assertSee('50.00');
        $usdResponse->assertDontSee('100.00');
    }

    public function test_cancelled_purchases_are_excluded_from_average(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Cancelled Ref Product', 'stock' => 10]);
        $supplier = Supplier::create(['name' => 'Cancelled Supplier', 'phone' => '0700000003']);

        $this->addPurchaseItem($this->makePurchase($user, $supplier, ['status' => 'cancelled']), $product, 10, '90.00');
        $this->addPurchaseItem($this->makePurchase($user, $supplier), $product, 10, '10.00');

        $response = $this->get('/reports/stock');

        $response->assertOk();
        // Only the 10.00 purchase counts — the cancelled 90.00 line must not.
        $response->assertSee('10.00');
        $response->assertSee('100.00');
        $response->assertDontSee('90.00');
    }

    public function test_low_stock_products_are_flagged_and_listed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeProduct($user, ['name' => 'Low Product', 'stock' => 5]);
        $this->makeProduct($user, ['name' => 'Healthy Product', 'stock' => 15]);
        $this->makeProduct($user, ['name' => 'Edge Product', 'stock' => 10]);

        $response = $this->get('/reports/stock');

        $response->assertOk();
        // The low-stock banner renders name and stock as separate spans.
        $response->assertSee('Low Product');
        $response->assertSee('(5)', false);
        $response->assertSee(__('messages.low_stock_short'));
        // Exactly at the threshold is NOT low — no banner chip for it.
        $response->assertDontSee('(10)', false);
    }

    public function test_zero_pools_are_not_flagged_as_low_when_switching_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // AFN-only product with a healthy AFN pool: its untouched USD pool (0)
        // must not be reported as low stock in the USD view, and the thin AFN
        // pool must still be flagged in the AFN view.
        $this->makeProduct($user, ['name' => 'Afn Only Healthy', 'stock' => 15]);
        $this->makeProduct($user, ['name' => 'Afn Only Low', 'stock' => 3]);

        $usdResponse = $this->get('/reports/stock?currency=USD');

        $usdResponse->assertOk();
        // No banner, no restock notice, and none of the zero pools chipped as low.
        $usdResponse->assertDontSee(__('messages.needs_restock'));
        $usdResponse->assertDontSee('(3)', false);

        $afnResponse = $this->get('/reports/stock?currency=AFN');

        $afnResponse->assertOk();
        $afnResponse->assertSee('Afn Only Low');
        $afnResponse->assertSee('(3)', false);
        $afnResponse->assertDontSee('(15)', false);
    }

    public function test_other_users_data_never_leaks_into_the_report(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userB);
        $productB = $this->makeProduct($userB, ['name' => 'Other User Product', 'stock' => 99]);
        $supplierB = Supplier::create(['name' => 'Other User Supplier', 'phone' => '0700000004']);
        $this->addPurchaseItem($this->makePurchase($userB, $supplierB), $productB, 99, '999.00');

        $this->actingAs($userA);
        $this->makeProduct($userA, ['name' => 'My Product', 'stock' => 10]);

        $response = $this->get('/reports/stock');

        $response->assertOk();
        $response->assertSee('My Product');
        $response->assertDontSee('Other User Product');
    }
}
