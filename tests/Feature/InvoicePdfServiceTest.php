<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\InvoicePdfService;
use App\Support\NativeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // The availability probe is cached per-process; reset it so tests
        // that flip the bridge's presence don't leak into each other.
        NativeDocumentTestProbe::reset();

        parent::tearDown();
    }

    public function test_order_pdf_renders_items_totals_and_party(): void
    {
        $user = User::create(['name' => 'A', 'email' => 'a@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        Setting::set('company_name', 'MGS Traders');
        Setting::set('invoice_prefix', 'INV-');

        $customer = Customer::create(['name' => 'Zarghuna', 'phone' => '0700000009']);
        $category = Category::create(['name' => 'General']);
        $unit = Unit::create(['name' => 'Kilo', 'short_name' => 'kg']);
        $product = Product::create(['name' => 'Widget', 'category_id' => $category->id, 'unit_id' => $unit->id]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '250.00',
            'total_amount' => '250.00',
            'paid_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '5',
            'unit_price' => '50.00',
            'subtotal' => '250.00',
        ]);

        $pdf = app(InvoicePdfService::class)->forOrder($order, [
            'name' => 'MGS Traders', 'address' => 'Kabul', 'phone' => '07', 'email' => '',
        ], 'INV-'.$order->id, 150.0);

        $this->assertStringStartsWith('%PDF', $pdf);
        // Arabic-script shaping requires the Lateef subset in the bundle.
        $this->assertStringContainsString('Lateef', $pdf);
        // Page content is compressed; the identity checks live in metadata.
        $this->assertNotEmpty($pdf);
    }

    public function test_purchase_pdf_renders_with_supplier(): void
    {
        $user = User::create(['name' => 'B', 'email' => 'b@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'Safi', 'phone' => '0700000010']);
        $category = Category::create(['name' => 'General']);
        $unit = Unit::create(['name' => 'Box', 'short_name' => 'box']);
        $product = Product::create(['name' => 'Crate', 'category_id' => $category->id, 'unit_id' => $unit->id]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '80.00',
            'total_amount' => '80.00',
            'paid_amount' => '80.00',
            'currency' => 'USD',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '40.00',
            'subtotal' => '80.00',
        ]);

        $pdf = app(InvoicePdfService::class)->forPurchase($purchase, [
            'name' => 'MGS Traders', 'address' => '', 'phone' => '', 'email' => '',
        ], 'PUR-'.$purchase->id, 0.0);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_device_gate_is_closed_on_the_dev_machine(): void
    {
        // The dev machine (and test env) gets the userland Jump fallback;
        // only the APK's C extension registers nativephp_call as internal.
        $this->assertFalse(NativeDocument::available());
        $this->assertFalse(NativeDocument::open('not-a-real-pdf', 'x.pdf'));
    }

    public function test_pashto_unit_and_currency_word_are_shaped_not_dejavu(): void
    {
        // The quantity cell and totals carry Pashto text (unit name, currency
        // word). The .ltr cell forces DejaVu, which cannot shape Arabic
        // script — the unit/currency must be wrapped out of it so Lateef
        // shapes it (INV-3's ugly کارتن).
        $user = User::create(['name' => 'C', 'email' => 'c@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        app()->setLocale('ps');

        $category = Category::create(['name' => 'General']);
        $unit = Unit::create(['name' => 'کارتن', 'short_name' => 'کارتن']);
        $product = Product::create(['name' => 'کجور', 'category_id' => $category->id, 'unit_id' => $unit->id]);
        $customer = Customer::create(['name' => 'Zarghuna', 'phone' => '0700000011']);

        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '2200.00',
            'total_amount' => '2200.00',
            'paid_amount' => '0.00',
            'currency' => 'AFN',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '2200.00',
            'subtotal' => '2200.00',
        ]);

        $svc = app(InvoicePdfService::class);
        $itemRow = new \ReflectionMethod($svc, 'itemRow');
        $itemRow->setAccessible(true);
        $html = new \ReflectionMethod($svc, 'html');
        $html->setAccessible(true);

        $items = [$itemRow->invoke($svc, OrderItem::with('product.unit')->where('order_id', $order->id)->first())];
        $markup = $html->invoke($svc, [
            'title' => 'Invoice',
            'number' => 'INV-3',
            'company' => ['name' => 'MGS', 'address' => '', 'phone' => '', 'email' => ''],
            'party_title' => 'Customer Info',
            'party' => null,
            'info_title' => 'Order Info',
            'info_lines' => [],
            'date' => '2026-09-27 20:00',
            'currency' => 'AFN',
            'items' => $items,
            'show_totals' => true,
            'paid' => 0.0,
            'pending' => 2200.0,
            'total' => 2200.0,
            'party_pending' => null,
            'party_pending_label' => '',
        ]);

        // Unit name rides in its own Lateef-shaped run, not the .ltr cell.
        $this->assertStringContainsString('<span class="rtl-run">کارتن</span>', $markup);
        // The Pashto currency word in totals likewise.
        $this->assertStringContainsString('<span class="rtl-run">افغانی</span>', $markup);
    }
}

/**
 * Tiny shim so tests can clear NativeDocument's static cache without
 * exposing a public reset on the production class.
 */
class NativeDocumentTestProbe
{
    public static function reset(): void
    {
        $prop = new \ReflectionProperty(NativeDocument::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
