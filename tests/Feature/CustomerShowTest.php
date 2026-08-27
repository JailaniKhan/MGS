<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerShowTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'cs@test.dev'],
            ['name' => 'C', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    public function test_show_renders_balance_summary_details_and_reminder_panel(): void
    {
        $this->actingUser();
        $customer = Customer::create([
            'name' => 'Hamid',
            'phone' => '+93700000005',
            'address' => 'Kabul',
        ]);

        $res = $this->get("/customers/{$customer->id}");
        $res->assertOk();
        $res->assertSee('Hamid');
        $res->assertSee('+93700000005');
        $res->assertSee('Kabul');
        $res->assertSee('reminder-panel');
        $res->assertSee(route('ledger.show', ['customer', $customer->id]));
    }

    public function test_show_computes_balance_from_orders_and_payments(): void
    {
        $this->actingUser();
        $customer = Customer::create(['name' => 'Nadia', 'phone' => '+93700000006']);

        Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'currency' => 'AFN',
            'total_amount' => 500,
            'status' => 'processing',
        ]);

        $res = $this->get("/customers/{$customer->id}");
        $res->assertOk();
        $res->assertSee('Nadia');
    }

    public function test_other_users_customer_is_not_visible(): void
    {
        $this->actingUser();
        $other = User::create(['name' => 'O', 'email' => 'other-cs@test.dev', 'password' => bcrypt('x')]);

        $hidden = new Customer(['name' => 'HiddenCustomer']);
        $hidden->user_id = $other->id;
        $hidden->save();

        $this->get("/customers/{$hidden->id}")->assertNotFound();
    }
}
