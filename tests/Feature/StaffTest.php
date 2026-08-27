<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::firstOrCreate(
            ['email' => 'staff@test.dev'],
            ['name' => 'S', 'password' => bcrypt('x')]
        );
    }

    protected function employee(array $attrs = []): Employee
    {
        return Employee::create(array_merge([
            'user_id' => $this->user()->id,
            'name' => 'Ahmad Wali',
            'phone' => '0700123456',
            'position' => 'Cashier',
            'monthly_salary' => 15000,
            'currency' => 'AFN',
        ], $attrs));
    }

    public function test_index_renders_payroll_summary_and_search(): void
    {
        $this->actingAs($this->user());
        $this->employee();
        $this->employee(['name' => 'Zia', 'currency' => 'USD', 'monthly_salary' => 200]);

        $res = $this->get('/staff');
        $res->assertOk();
        $res->assertSee('staff-list');
        $res->assertSee('data-list-filter="staff-list"', false);
        $res->assertSee('Ahmad Wali');
        $res->assertSee('15,000');
        $res->assertSee('200');
    }

    public function test_show_renders_profile_record_salary_form_and_history(): void
    {
        $this->actingAs($this->user());
        $emp = $this->employee();
        SalaryPayment::create([
            'employee_id' => $emp->id,
            'amount' => 15000,
            'currency' => 'AFN',
            'for_month' => '2026-08-01',
            'notes' => 'full month',
        ]);

        $res = $this->get("/staff/{$emp->id}");
        $res->assertOk();
        $res->assertSee('Ahmad Wali');
        $res->assertSee('salary-currency-radio', false);
        $res->assertSee('name="for_month"', false);
        $res->assertSee('2026/08');
        $res->assertSee("staff/{$emp->id}/edit");
    }

    public function test_create_renders_searchable_currency_chips_and_icons(): void
    {
        $res = $this->actingAs($this->user())->get('/staff/create');
        $res->assertOk();
        $res->assertSee('staff-currency-radio', false);
        $res->assertSee('name="monthly_salary"', false);
        $res->assertSee('name="position"', false);
    }

    public function test_store_creates_employee(): void
    {
        $user = $this->user();

        $res = $this->actingAs($user)->post('/staff', [
            'name' => 'Karim',
            'phone' => '0700000001',
            'position' => 'Guard',
            'monthly_salary' => '8000',
            'currency' => 'AFN',
        ]);

        $res->assertRedirect(route('staff.index'));
        $res->assertSessionHas('success');
        $this->assertDatabaseHas('employees', ['name' => 'Karim', 'position' => 'Guard']);
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $this->actingAs($this->user())->post('/staff', [
            'name' => '',
            'monthly_salary' => '-5',
            'currency' => 'EUR',
        ])->assertSessionHasErrors(['name', 'monthly_salary', 'currency']);
    }
}
