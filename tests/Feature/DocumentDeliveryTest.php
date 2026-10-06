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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Printed documents (order invoices, purchase bills, cashbook statements) and
 * the three ways they leave the phone: view, save into Downloads/MGS, and
 * WhatsApp. The device gate itself is covered by InvoicePdfServiceTest; these
 * tests cover the browser fallback, the preview pages and the gateway send.
 */
class DocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openwa.base_url' => 'http://openwa.test',
            'services.openwa.api_key' => 'test-api-key',
            'services.openwa.session' => 'default',
        ]);
    }

    private function actingUser(): User
    {
        $user = User::create(['name' => 'Doc Tester', 'email' => 'docs@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        Setting::set('company_name', 'MGS Traders');

        return $user;
    }

    private function makeOrder(): Order
    {
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

        return $order;
    }

    private function makePurchase(): Purchase
    {
        $supplier = Supplier::create(['name' => 'Safi', 'phone' => '0700000010']);
        $category = Category::create(['name' => 'General Purchase']);
        $unit = Unit::create(['name' => 'Box', 'short_name' => 'box']);
        $product = Product::create(['name' => 'Crate', 'category_id' => $category->id, 'unit_id' => $unit->id]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '80.00',
            'total_amount' => '80.00',
            'paid_amount' => '40.00',
            'currency' => 'USD',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '40.00',
            'subtotal' => '80.00',
        ]);

        return $purchase;
    }

    public function test_order_print_preview_renders_a_valid_table_header(): void
    {
        $this->actingUser();
        $order = $this->makeOrder();

        $this->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('<thead>', false)
            ->assertSee('</thead>', false)
            // The old markup folded the header row into a bogus <th> — the
            // regression this guards against.
            ->assertDontSee('scope="col"ead', false)
            // Browsers keep the classic print pipeline.
            ->assertSee('window.print()', false);
    }

    public function test_purchase_print_preview_renders_a_valid_table_header(): void
    {
        $this->actingUser();
        $purchase = $this->makePurchase();

        $this->get(route('purchases.print', $purchase))
            ->assertOk()
            ->assertSee('<thead>', false)
            ->assertDontSee('scope="col"ead', false);
    }

    public function test_cashbook_statement_preview_renders(): void
    {
        $this->actingUser();
        $customer = Customer::create(['name' => 'Statement Customer', 'phone' => '0700000011']);

        $this->get(route('cashbook.print', ['customer', $customer->id]))
            ->assertOk()
            ->assertSee(__('messages.statement'))
            ->assertSee('Statement Customer')
            ->assertSee('<thead>', false);
    }

    public function test_save_falls_back_to_a_pdf_download_in_the_browser(): void
    {
        $this->actingUser();
        $order = $this->makeOrder();

        // No bridge on a desktop browser, so the bytes come back as a download.
        $response = $this->post(route('orders.pdf.save', $order));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Regression: the device action endpoints are POST-only, and the APK's
     * WebView follows any 3xx from them as a GET. If a handler redirects back
     * to its own URL (which back() does when the Referer is unusable), the
     * WebView re-requests a POST-only route with GET and the app dies on a 405.
     *
     * The page each action returns to must therefore be a GET-able route. These
     * tests pin that: the return page exists, is GET, and is not the action URL.
     */
    public function test_pdf_actions_return_to_a_gettable_preview_page(): void
    {
        $this->actingUser();
        $order = $this->makeOrder();
        $purchase = $this->makePurchase();
        $customer = Customer::create(['name' => 'Return Page', 'phone' => '0700000012']);

        $returns = [
            route('orders.pdf.open', $order) => route('orders.print', $order),
            route('orders.pdf.save', $order) => route('orders.print', $order),
            route('orders.pdf.share', $order) => route('orders.print', $order),
            route('purchases.pdf.open', $purchase) => route('purchases.print', $purchase),
            route('purchases.pdf.save', $purchase) => route('purchases.print', $purchase),
            route('purchases.pdf.share', $purchase) => route('purchases.print', $purchase),
        ];

        foreach ($returns as $action => $return) {
            $this->assertNotSame($action, $return, 'A PDF action must not redirect to itself.');
            $this->assertContains('GET', app('router')->getRoutes()->match(
                Request::create($return, 'GET')
            )->methods(), "{$return} must be reachable with GET.");
        }

        // Cashbook statements return to their preview page too.
        $cashbookReturn = route('cashbook.print', ['customer', $customer->id]);
        $this->assertContains('GET', app('router')->getRoutes()->match(
            Request::create($cashbookReturn, 'GET')
        )->methods());
    }

    public function test_whatsapp_pdf_send_delivers_a_document_and_logs_the_reminder(): void
    {
        $this->actingUser();
        $order = $this->makeOrder();

        Http::fake([
            'openwa.test/api/sessions/default' => Http::response(['status' => 'ready'], 200),
            'openwa.test/api/sessions/default/messages/send-text' => Http::response(['status' => 'sent', 'id' => 'cap1'], 200),
            'openwa.test/api/sessions/default/messages/send-document' => Http::response(['status' => 'sent', 'id' => 'doc1'], 200),
        ]);

        $this->post(route('orders.pdf.whatsapp', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_ends_with($request->url(), '/messages/send-document')
                && $body['chatId'] === '93700000009@c.us'
                && str_starts_with($body['document'], 'data:application/pdf;base64,')
                && str_ends_with($body['fileName'], '.pdf');
        });

        // The document is kept in the chat history like every other message.
        $this->assertDatabaseHas('reminders', [
            'status' => 'sent',
            'channel' => 'whatsapp',
            'media_type' => 'application/pdf',
            'provider_message_id' => 'doc1',
        ]);
    }

    public function test_whatsapp_pdf_send_fails_cleanly_without_a_gateway(): void
    {
        $this->actingUser();
        $order = $this->makeOrder();

        config(['services.openwa.api_key' => null]);

        $this->post(route('orders.pdf.whatsapp', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('reminders', [
            'status' => 'failed',
            'media_type' => 'application/pdf',
        ]);
    }

    public function test_statement_pdf_renders_with_the_cashbook_rows(): void
    {
        $this->actingUser();

        $customer = Customer::create(['name' => 'Statement Customer', 'phone' => '0700000011']);

        $transactions = collect([
            (object) [
                'direction' => 'in',
                'label' => 'Cash in — sale',
                'date' => now()->subDays(2),
                'amount' => '150.00',
                'notes' => '',
                'balance' => '150.00',
            ],
            (object) [
                'direction' => 'out',
                'label' => 'Cash out — purchase',
                'date' => now()->subDay(),
                'amount' => '50.00',
                'notes' => 'paid in full',
                'balance' => '100.00',
            ],
        ]);

        $pdf = app(InvoicePdfService::class)->forStatement(
            $customer,
            'customer',
            'AFN',
            ['AFN' => ['in' => 150.0, 'out' => 50.0, 'net' => 100.0]],
            $transactions,
            100.0,
            ['name' => 'MGS Traders', 'address' => 'Kabul', 'phone' => '07', 'email' => ''],
            'ST-CUS-'.$customer->id,
        );

        $this->assertStringStartsWith('%PDF', $pdf);
        // Arabic-script shaping requires the Lateef subset in the bundle.
        $this->assertStringContainsString('Lateef', $pdf);
    }
}
