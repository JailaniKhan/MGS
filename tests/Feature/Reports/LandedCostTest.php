<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandedCostTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(string $name): Product
    {
        $category = Category::create(['name' => 'Landed']);

        return Product::create(['name' => $name, 'category_id' => $category->id]);
    }

    private function makePurchase(User $user, Supplier $supplier, array $overrides = []): Purchase
    {
        $this->actingAs($user);

        return Purchase::create(array_merge([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => 'USD',
        ], $overrides));
    }

    private function purchaseLine(Purchase $purchase, Product $product, int $qty, string $unitPrice, ?string $lot = null): void
    {
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => bcmul($unitPrice, (string) $qty, 2),
            'lot_number' => $lot,
        ]);
    }

    private function linkedExpense(User $user, Purchase $purchase, string $amount, string $date = '2026-08-20'): Expense
    {
        $this->actingAs($user);

        return Expense::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'category' => 'Freight',
            'amount' => $amount,
            'currency' => $purchase->currency,
            'expense_date' => $date,
        ]);
    }

    public function test_landed_cost_allocates_by_line_value(): void
    {
        $user = $this->seedUser();
        $this->actingAs($user);
        $supplier = Supplier::create(['name' => 'Freight Supplier', 'phone' => '0700000200']);
        $productA = $this->makeProduct('Truck A');
        $productB = $this->makeProduct('Truck B');

        // Worked example: A = 1,000 (10u), B = 100 (1u), freight 110.
        // A share = 100/1100 * 110 = 100 -> landed unit cost = (1000+100)/10 = 110.
        // B share = 10 -> landed unit cost = (100+10)/1 = 110.
        $purchase = $this->makePurchase($user, $supplier, [
            'subtotal' => '1100.00', 'total_amount' => '1100.00',
        ]);
        $purchase->forceFill(['created_at' => '2026-08-18 10:00:00'])->save();
        $this->purchaseLine($purchase, $productA, 10, '100.00', '1');
        $this->purchaseLine($purchase, $productB, 1, '100.00', '1');
        $this->linkedExpense($user, $purchase, '110.00');

        // Sell 1 of A in the same window so the unit P/L exposes the cost.
        $customer = Customer::create(['name' => 'Landed Buyer', 'phone' => '0700000201']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '120.00',
            'total_amount' => '120.00',
            'currency' => 'USD',
        ]);
        $order->forceFill(['created_at' => '2026-08-25 10:00:00'])->save();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 1,
            'unit_price' => '120.00',
            'subtotal' => '120.00',
        ]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-30&currency=USD');

        $response->assertOk();
        // COGS = 110 (landed-inclusive), rendered as an expense row.
        $response->assertSee('>-110.00', false);
        // Operating expenses exclude the linked freight.
        $response->assertDontSee('>-110.00 '.__('messages.land'), false);
        // Gross profit = 120 - 110 = 10; net profit = 10 (no other expenses).
        $response->assertSee('+10.00', false);
    }

    public function test_linked_expense_of_cancelled_purchase_stays_out_of_operating(): void
    {
        $user = $this->seedUser();
        $this->actingAs($user);
        $supplier = Supplier::create(['name' => 'Cancelled Freight Sup', 'phone' => '0700000202']);
        $product = $this->makeProduct('Cancelled Freight Item');

        $purchase = $this->makePurchase($user, $supplier, [
            'status' => 'cancelled',
            'subtotal' => '500.00', 'total_amount' => '500.00',
        ]);
        $this->purchaseLine($purchase, $product, 5, '100.00');
        $this->linkedExpense($user, $purchase, '50.00');

        $response = $this->get('/reports/profit-loss?date_to=2026-08-30&currency=USD');

        $response->assertOk();
        // Cancelled purchase's freight never lands in COGS.
        $response->assertDontSee('>-50.00', false);
    }

    public function test_unlinked_expense_stays_in_operating_expenses(): void
    {
        $user = $this->seedUser();
        $this->actingAs($user);

        Expense::create([
            'user_id' => $user->id,
            'category' => 'Rent',
            'amount' => '75.00',
            'currency' => 'USD',
            'expense_date' => '2026-08-21',
        ]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-30&currency=USD');

        $response->assertOk();
        // Unlinked expense remains a plain operating expense row.
        $response->assertSee('>-75.00', false);
    }
}
