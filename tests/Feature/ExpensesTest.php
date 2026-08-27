<?php

namespace Tests\Feature;

use App\Models\Expense;
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

    public function test_index_renders_month_summary_tiles(): void
    {
        $user = $this->user();

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
            'expense_date' => now(),
        ]);

        $res = $this->actingAs($user)->get('/expenses');
        $res->assertOk();
        $res->assertSee('expense-list');
        $res->assertSee('data-list-filter="expense-list"', false);
        $res->assertSee('Rent');
        $res->assertSee('1,201');
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

    public function test_create_renders_searchable_category_and_brand_chips(): void
    {
        $res = $this->actingAs($this->user())->get('/expenses/create');
        $res->assertOk();
        $res->assertSee('data-searchable', false);
        $res->assertSee('name="category"', false);
        $res->assertSee('category_select');
        $res->assertSee('has-[:checked]:border-brand', false);
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
}
