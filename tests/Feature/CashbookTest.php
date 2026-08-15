<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CashbookTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function makeSupplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function postEntry(array $overrides = []): TestResponse
    {
        return $this->post(route('cashbook.store'), array_merge([
            'type' => 'in',
            'amount' => '100.00',
            'currency' => 'AFN',
            'entry_date' => now()->toDateString(),
            'person' => '',
            'notes' => '',
        ], $overrides));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('cashbook.index'))->assertRedirect(route('login'));
        $this->get(route('cashbook.create'))->assertRedirect(route('login'));
        $this->get(route('cashbook.person', ['customer', 1]))->assertRedirect(route('login'));
        $this->post(route('cashbook.store'))->assertRedirect(route('login'));
    }

    public function test_store_records_cashbook_in_and_increases_cash_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postEntry(['type' => 'in', 'amount' => '250.00']);

        $response->assertRedirect(route('cashbook.index'));
        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $user->id,
            'source' => 'cashbook_in',
            'reference_type' => null,
            'reference_id' => null,
        ]);
        $this->assertDatabaseHas('ledger_entries', ['direction' => 'credit', 'amount' => '250.00']);

        $this->get(route('cashbook.index'))->assertSee('250.00', false);
    }

    public function test_store_records_cashbook_out_as_cash_debit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postEntry(['type' => 'out', 'amount' => '80.00']);

        $response->assertRedirect(route('cashbook.index'));
        $this->assertDatabaseHas('journal_entries', ['source' => 'cashbook_out']);
        $this->assertDatabaseHas('ledger_entries', ['direction' => 'debit', 'amount' => '80.00']);
    }

    public function test_store_links_entry_to_person_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Referenced Customer');

        $response = $this->postEntry(['amount' => '50.00', 'person' => 'customer:'.$customer->id]);

        $response->assertRedirect(route('cashbook.index'));
        $this->assertDatabaseHas('journal_entries', [
            'source' => 'cashbook_in',
            'reference_type' => 'customer',
            'reference_id' => $customer->id,
        ]);
    }

    public function test_store_rejects_mismatched_person_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Supplier Person');

        $response = $this->postEntry(['person' => 'customer:'.$supplier->id]);

        $response->assertSessionHasErrors('person');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_store_rejects_amount_below_minimum(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postEntry(['amount' => '0.00'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_index_groups_entries_by_person_and_shows_net_per_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $alpha = $this->makeCustomer('Alpha Cash');
        $beta = $this->makeSupplier('Beta Cash');

        $this->postEntry(['type' => 'in', 'amount' => '100.00', 'person' => 'customer:'.$alpha->id]);
        $this->postEntry(['type' => 'in', 'amount' => '50.00', 'person' => 'customer:'.$alpha->id]);
        $this->postEntry(['type' => 'out', 'amount' => '40.00', 'person' => 'customer:'.$alpha->id]);
        $this->postEntry(['type' => 'in', 'amount' => '200.00', 'currency' => 'USD', 'person' => 'supplier:'.$beta->id]);

        $response = $this->get(route('cashbook.index'));

        $response->assertOk();
        $response->assertSee('Alpha Cash');
        $response->assertSee('Beta Cash');
        $response->assertSee('110.00', false);
        $response->assertSee('200.00', false);
    }

    public function test_create_shows_searchable_person_options(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->makeCustomer('Pickable Customer');

        $response = $this->get(route('cashbook.create'));

        $response->assertOk();
        $response->assertSee('Pickable Customer');
        $response->assertSee(__('messages.select_person'));
    }

    public function test_person_page_shows_cashbook_net_and_entry_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Person Detail');
        $this->postEntry(['type' => 'in', 'amount' => '100.00', 'person' => 'customer:'.$customer->id]);
        $this->postEntry(['type' => 'in', 'amount' => '50.00', 'person' => 'customer:'.$customer->id]);
        $this->postEntry(['type' => 'out', 'amount' => '20.00', 'person' => 'customer:'.$customer->id]);

        $response = $this->get(route('cashbook.person', ['customer', $customer->id]));

        $response->assertOk();
        $response->assertSee('Person Detail');
        $response->assertSee(__('messages.cashbook_net'));
        $response->assertSee('130.00', false);
        $response->assertSee('100.00', false);
        $response->assertSee('20.00', false);
    }

    public function test_person_page_excludes_cancelled_documents_from_outstanding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Cancelled Doc');
        $live = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        Payment::create(['order_id' => $live->id, 'amount' => '300.00', 'currency' => 'AFN']);

        Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'cancelled',
            'subtotal' => '0.00',
            'total_amount' => '900.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get(route('cashbook.person', ['customer', $customer->id]));

        $response->assertOk();
        $response->assertSee('200.00', false);
        $response->assertDontSee('900.00');
    }

    public function test_person_page_shows_purchase_outstanding_for_supplier(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Supplier Docs');
        Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '700.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get(route('cashbook.person', ['supplier', $supplier->id]));

        $response->assertOk();
        $response->assertSee('Supplier Docs');
        $response->assertSee('700.00', false);
    }
}
