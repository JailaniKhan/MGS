<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierShowTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'ss@test.dev'],
            ['name' => 'S', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    public function test_show_renders_balance_summary_details_and_links(): void
    {
        $this->actingUser();
        $supplier = Supplier::create([
            'name' => 'Wholesaler',
            'phone' => '+93700000015',
            'address' => 'Herat',
        ]);

        $res = $this->get("/suppliers/{$supplier->id}");
        $res->assertOk();
        $res->assertSee('Wholesaler');
        $res->assertSee('+93700000015');
        $res->assertSee('Herat');
        $res->assertSee(route('ledger.show', ['supplier', $supplier->id]));
        $res->assertSee(route('whatsapp.chats.show', ['supplier', $supplier->id]));
    }

    public function test_show_computes_balance_from_purchases_and_payments(): void
    {
        $this->actingUser();
        $supplier = Supplier::create(['name' => 'Ganj', 'phone' => '+93700000016']);

        Purchase::create([
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'supplier_id' => $supplier->id,
            'currency' => 'AFN',
            'total_amount' => 800,
            'status' => 'pending',
        ]);

        Purchase::create([
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'total_amount' => 50,
            'status' => 'pending',
        ]);

        $res = $this->get("/suppliers/{$supplier->id}");
        $res->assertOk();
        $res->assertSee('Ganj');
        $res->assertSee(route('purchases.show', 1));
        // Both currencies headline the balance card, AFN and USD alike.
        $res->assertSee(__('messages.remaining'));
        $res->assertSee(__('messages.afn'));
        $res->assertSee(__('messages.usd'));
        $res->assertSee('800');
        $res->assertSee('50');
    }

    public function test_other_users_supplier_is_not_visible(): void
    {
        $this->actingUser();
        $other = User::create(['name' => 'O', 'email' => 'other-ss@test.dev', 'password' => bcrypt('x')]);

        $hidden = new Supplier(['name' => 'HiddenSupplier']);
        $hidden->user_id = $other->id;
        $hidden->save();

        $this->get("/suppliers/{$hidden->id}")->assertNotFound();
    }
}
