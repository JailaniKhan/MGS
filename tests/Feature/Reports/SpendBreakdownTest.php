<?php

namespace Tests\Feature\Reports;

use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpense(User $user, array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'category' => 'Transport',
            'amount' => '100.00',
            'currency' => 'AFN',
            'expense_date' => Carbon::now()->toDateString(),
        ], $overrides));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/spend-breakdown')->assertRedirect(route('login'));
    }

    public function test_default_month_period_renders_single_anchor_date_input(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/spend-breakdown');

        $response->assertOk();
        $response->assertSee('value="'.Carbon::now()->toDateString().'"', false);
        $response->assertDontSee('name="date_to"');
    }

    public function test_month_period_includes_expenses_on_any_day_of_the_month(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeExpense($user, [
            'category' => 'Electricity',
            'amount' => '150.00',
            'expense_date' => '2026-08-12 10:00:00',
        ]);
        $this->makeExpense($user, [
            'category' => 'Water',
            'amount' => '40.00',
            'expense_date' => '2026-08-31 15:00:00',
        ]);

        $response = $this->get('/spend-breakdown?period=month&date_from=2026-08-01');

        $response->assertOk();
        $response->assertSee('value="2026-08-01"', false);
        $response->assertDontSee('name="date_to"');
        $response->assertSee('Electricity');
        $response->assertSee('150');
        $response->assertSee('Water');
        $response->assertSee('40');
    }

    public function test_month_period_includes_date_only_expense_on_the_first_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Stored date-only, as received from an <input type="date">. A `<=`
        // endOfDay string bound would drop this day from the range.
        $this->makeExpense($user, ['amount' => '25.00', 'expense_date' => '2026-08-01']);

        $response = $this->get('/spend-breakdown?period=month&date_from=2026-08-01');

        $response->assertOk();
        $response->assertSee('>25.00<', false);
    }

    public function test_year_period_spans_the_whole_year_around_the_anchor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeExpense($user, ['amount' => '100.00', 'expense_date' => '2026-01-01 08:00:00']);
        $this->makeExpense($user, ['amount' => '250.00', 'expense_date' => '2026-12-31 17:30:00']);

        $response = $this->get('/spend-breakdown?period=year&date_from=2026-06-15');

        $response->assertOk();
        $response->assertSee('>350.00<', false);
    }

    public function test_week_period_derives_its_range_from_the_anchor_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeExpense($user, ['amount' => '60.00', 'expense_date' => '2026-08-03 09:00:00']);
        $this->makeExpense($user, ['amount' => '90.00', 'expense_date' => '2026-08-10 09:00:00']);

        $response = $this->get('/spend-breakdown?period=week&date_from=2026-08-05');

        $response->assertOk();
        $response->assertSee('>60.00<', false);
        $response->assertDontSee('>150.00<', false);
    }

    public function test_category_grouping_keeps_currencies_separate_and_sums_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeExpense($user, ['category' => 'Rent', 'amount' => '100.00', 'currency' => 'AFN', 'expense_date' => '2026-08-02']);
        $this->makeExpense($user, ['category' => 'Rent', 'amount' => '50.00', 'currency' => 'AFN', 'expense_date' => '2026-08-03']);
        $this->makeExpense($user, ['category' => 'Rent', 'amount' => '20.00', 'currency' => 'USD', 'expense_date' => '2026-08-04']);
        $this->makeExpense($user, ['category' => 'Food', 'amount' => '30.00', 'currency' => 'AFN', 'expense_date' => '2026-08-05']);

        $response = $this->get('/spend-breakdown?period=month&date_from=2026-08-01');

        $response->assertOk();
        $response->assertSee('>180.00<', false);
        $response->assertSee('>20.00<', false);
        $response->assertSee('3 '.__('messages.transactions'));
        $response->assertSeeInOrder(['Rent', 'Food']);
    }

    public function test_empty_state_shows_without_chart_when_no_expenses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/spend-breakdown');

        $response->assertOk();
        $response->assertSee(__('messages.no_expenses'));
        $response->assertDontSee('dailyTrendChart');
    }

    public function test_daily_trend_chart_renders_when_expenses_exist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->makeExpense($user, ['amount' => '75.00', 'expense_date' => '2026-08-05']);
        $this->makeExpense($user, ['amount' => '25.00', 'currency' => 'USD', 'expense_date' => '2026-08-05']);

        $response = $this->get('/spend-breakdown?period=month&date_from=2026-08-01');

        $response->assertOk();
        $response->assertSee('dailyTrendChart');
        $response->assertSee('new Chart');
    }
}
