<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::create(['name' => 'E', 'email' => 'expenses@test.dev', 'password' => bcrypt('x')]);
    }

    public function test_index_renders_all_time_summary_tiles(): void
    {
        $user = $this->user();

        // An old expense (outside any "this month" window) must still count.
        Expense::create([
            'user_id' => $user->id,
            'category' => 'Rent',
            'amount' => 1200.50,
            'currency' => 'AFN',
            'expense_date' => now()->startOfMonth()->addDay(),
        ]);
        Expense::create([
            'user_id' => $user->id,
            'category' => 'Fuel',
            'amount' => 50,
            'currency' => 'USD',
            'expense_date' => now()->subYear(),
        ]);

        $res = $this->actingAs($user)->get('/expenses');
        $res->assertOk();
        $res->assertSee('expense-list');
        $res->assertSee('data-list-filter="expense-list"', false);
        $res->assertSee('Rent');
        // The summary is all-time: both expenses land in the tiles/hero.
        $res->assertSee(__('messages.all_time'));
        $res->assertSee('1,201');
        $res->assertSee('50');
    }

    public function test_index_ignores_other_users_expenses_in_summary(): void
    {
        $user = $this->user();
        $other = User::create(['name' => 'O', 'email' => 'other@test.dev', 'password' => bcrypt('x')]);

        Expense::create([
            'user_id' => $other->id,
            'category' => 'Hidden',
            'amount' => 999,
            'currency' => 'AFN',
            'expense_date' => now(),
        ]);

        $res = $this->actingAs($user)->get('/expenses');
        $res->assertOk();
        $res->assertDontSee('Hidden');
        $res->assertDontSee('999');
    }

    public function test_create_renders_searchable_category_and_segmented_currency(): void
    {
        $res = $this->actingAs($this->user())->get('/expenses/create');
        $res->assertOk();
        $res->assertSee('data-searchable', false);
        $res->assertSee('name="category"', false);
        $res->assertSee('category_select');
        // Currency renders as a segmented control over the same radios.
        $res->assertSee('segmented-item', false);
        $res->assertSee('name="currency"', false);
        $res->assertSee('value="AFN"', false);
        $res->assertSee('value="USD"', false);
    }

    public function test_store_creates_expense_and_redirects(): void
    {
        $user = $this->user();

        $res = $this->actingAs($user)->post('/expenses', [
            'category' => 'Electricity',
            'amount' => '250.75',
            'currency' => 'AFN',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'bill',
            'receipt_path' => '',
        ]);

        $res->assertRedirect(route('expenses.index'));
        $res->assertSessionHas('success');
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category' => 'Electricity',
            'currency' => 'AFN',
        ]);
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $this->actingAs($this->user())->post('/expenses', [
            'category' => '',
            'amount' => '-1',
            'currency' => 'EUR',
        ])->assertSessionHasErrors(['category', 'amount', 'currency']);
    }

    private function makePurchase(User $user, array $overrides = []): Purchase
    {
        $supplier = Supplier::create(['name' => 'Exp Supplier', 'phone' => '0700000100']);

        $this->actingAs($user);

        return Purchase::create(array_merge([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '100.00',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ], $overrides));
    }

    public function test_store_attaches_expense_to_purchase(): void
    {
        $user = $this->user();
        $purchase = $this->makePurchase($user);

        $res = $this->actingAs($user)->post('/expenses', [
            'category' => 'Freight',
            'amount' => '110.00',
            'currency' => 'AFN',
            'expense_date' => now()->format('Y-m-d'),
            'purchase_id' => $purchase->id,
        ]);

        $res->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'category' => 'Freight',
            'amount' => '110.00',
        ]);
    }

    public function test_store_rejects_currency_mismatch_with_purchase(): void
    {
        $user = $this->user();
        $usdPurchase = $this->makePurchase($user, ['currency' => 'USD']);

        $this->actingAs($user)->post('/expenses', [
            'category' => 'Freight',
            'amount' => '50.00',
            'currency' => 'AFN',
            'expense_date' => now()->format('Y-m-d'),
            'purchase_id' => $usdPurchase->id,
        ])->assertSessionHasErrors(['purchase_id']);
    }

    public function test_store_rejects_other_users_purchase(): void
    {
        $user = $this->user();
        $other = User::create(['name' => 'O2', 'email' => 'other2@test.dev', 'password' => bcrypt('x')]);
        $foreignPurchase = $this->makePurchase($other);

        $this->actingAs($user)->post('/expenses', [
            'category' => 'Freight',
            'amount' => '50.00',
            'currency' => 'AFN',
            'expense_date' => now()->format('Y-m-d'),
            'purchase_id' => $foreignPurchase->id,
        ])->assertForbidden();
    }

    public function test_store_rejects_cancelled_purchase(): void
    {
        $user = $this->user();
        $cancelled = $this->makePurchase($user, ['status' => 'cancelled']);

        $this->actingAs($user)->post('/expenses', [
            'category' => 'Freight',
            'amount' => '50.00',
            'currency' => 'AFN',
            'expense_date' => now()->format('Y-m-d'),
            'purchase_id' => $cancelled->id,
        ])->assertSessionHasErrors(['purchase_id']);
    }

    public function test_update_can_unlink_purchase(): void
    {
        $user = $this->user();
        $purchase = $this->makePurchase($user);
        $expense = Expense::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'category' => 'Freight',
            'amount' => 110,
            'currency' => 'AFN',
            'expense_date' => now(),
        ]);

        $res = $this->actingAs($user)->put("/expenses/{$expense->id}", [
            'category' => 'Freight',
            'amount' => '110.00',
            'currency' => 'AFN',
            'expense_date' => now()->format('Y-m-d'),
            'purchase_id' => '',
        ]);

        $res->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'purchase_id' => null,
        ]);
    }

    public function test_index_shows_purchase_lot_chip_for_linked_expense(): void
    {
        $user = $this->user();
        $purchase = $this->makePurchase($user);
        $category = Category::create(['name' => 'Linked']);
        $product = Product::create(['name' => 'Linked Widget', 'category_id' => $category->id]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => '20.00',
            'subtotal' => '100.00',
            'lot_number' => '3',
        ]);
        Expense::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'category' => 'Freight',
            'amount' => 110,
            'currency' => 'AFN',
            'expense_date' => now(),
        ]);

        $res = $this->actingAs($user)->get('/expenses');

        $res->assertOk();
        $res->assertSee('#'.$purchase->id, false);
    }

    public function test_create_purchase_picker_shows_lot_products_and_purchase_number(): void
    {
        $user = $this->user();
        $purchase = $this->makePurchase($user, [
            'total_amount' => '100.00',
        ]);
        $category = Category::create(['name' => 'Picker']);
        $product = Product::create(['name' => 'Picker Widget', 'category_id' => $category->id]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => '20.00',
            'subtotal' => '100.00',
            'lot_number' => '7',
        ]);

        $res = $this->actingAs($user)->get('/expenses/create');

        $res->assertOk();
        // Option label carries lot + product name + purchase number; the option
        // data attributes feed the active chip with the same parts.
        $res->assertSee(__('messages.lot_short').' 7', false);
        $res->assertSee('Picker Widget');
        $res->assertSee('#'.$purchase->id, false);
        $res->assertSee('data-lots="7"', false);
        $res->assertSee('data-products="Picker Widget"', false);
        $res->assertSee('data-purchase-id="'.$purchase->id.'"', false);
    }
}
