<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Restoring a backup file: the shop picks a backup_*.json off the phone (or
 * uploads one in a browser), sees what it holds, then writes it back.
 *
 * The native picker itself is covered by NativePicker and the Kotlin bridge;
 * these tests cover staging, the confirmation screen and the import.
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $email = 'restore@test.dev'): User
    {
        $user = User::create(['name' => 'R', 'email' => $email, 'password' => bcrypt('x')]);
        $this->actingAs($user);

        return $user;
    }

    /** A small but connected shop: party → product → order → line. */
    private function seedShop(): array
    {
        $customer = Customer::create(['name' => 'Zarghuna', 'phone' => '0700000009']);
        $supplier = Supplier::create(['name' => 'Safi', 'phone' => '0700000010']);
        $category = Category::create(['name' => 'General']);
        $unit = Unit::create(['name' => 'Kilo', 'short_name' => 'kg']);
        $product = Product::create(['name' => 'Widget', 'category_id' => $category->id, 'unit_id' => $unit->id]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '100.00',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '50.00',
            'subtotal' => '100.00',
        ]);

        return compact('customer', 'supplier', 'category', 'unit', 'product', 'order');
    }

    /** Build a real archive through the controller, the way the shop would. */
    private function archive(): string
    {
        $this->post('/backup')->assertRedirect(route('backup.index'));

        $json = collect(Storage::disk('local')->files('backups/user_'.auth()->id()))
            ->first(fn ($file) => str_ends_with($file, '.json'));

        $this->assertNotNull($json, 'the archive was not written');

        return (string) Storage::disk('local')->get($json);
    }

    /** Upload an archive, then confirm it — returns the apply response. */
    private function restore(string $contents, string $mode = 'replace'): TestResponse
    {
        $staged = $this->post('/backup/restore', [
            'archive' => UploadedFile::fake()->createWithContent('backup.json', $contents),
        ])->assertRedirect();

        return $this->post(route('backup.restore.apply'), [
            'token' => $this->tokenFrom((string) $staged->headers->get('Location')),
            'mode' => $mode,
        ]);
    }

    /** The staged file's token, as it travels in the confirmation URL. */
    private function tokenFrom(string $location): string
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return (string) ($query['token'] ?? '');
    }

    /** Drop every document row, the way a fresh install looks. */
    private function wipeShop(): void
    {
        foreach (['order_items', 'orders', 'products', 'categories', 'units', 'customers', 'suppliers'] as $table) {
            DB::table($table)->delete();
        }
    }

    public function test_a_picked_archive_restores_the_shop_through_the_confirmation_screen(): void
    {
        Storage::fake('local');

        $this->actingUser();
        $shop = $this->seedShop();
        $contents = $this->archive();

        $this->wipeShop();
        $this->assertSame(0, Customer::count());

        $staged = $this->post('/backup/restore', [
            'archive' => UploadedFile::fake()->createWithContent('backup_2026_09_26_10_00_00.json', $contents),
        ])->assertRedirect();

        $token = $this->tokenFrom((string) $staged->headers->get('Location'));

        // Staged, not applied: nothing is written until the shop confirms.
        $this->assertSame(0, Customer::count());

        $this->get(route('backup.restore.preview', ['token' => $token]))
            ->assertOk()
            ->assertSee(__('messages.customers'))
            ->assertSee(__('messages.orders'));

        $this->post(route('backup.restore.apply'), ['token' => $token, 'mode' => 'replace'])
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('success');

        // Identifiers survive the round trip, so order → customer links hold.
        $this->assertSame(1, Customer::count());
        $this->assertSame('Zarghuna', Customer::first()->name);
        $this->assertSame($shop['customer']->id, Order::first()->person_id);
        $this->assertSame(1, OrderItem::count());

        // Staged copies are consumed, not left behind.
        $this->assertSame([], Storage::disk('local')->files('backups/restore'));
    }

    public function test_replace_drops_records_created_after_the_archive_and_leaves_other_accounts_alone(): void
    {
        Storage::fake('local');

        $this->actingUser();
        $this->seedShop();
        $contents = $this->archive();

        Customer::create(['name' => 'Later Customer', 'phone' => '0700000020']);

        // Another account's shop lives in the same database.
        $other = User::create(['name' => 'Other Shop', 'email' => 'other@test.dev', 'password' => bcrypt('x')]);
        DB::table('customers')->insert([
            'id' => 999,
            'user_id' => $other->id,
            'name' => 'Their Customer',
            'phone' => '0700000030',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->restore($contents)->assertSessionHas('success');

        $this->assertFalse(Customer::where('name', 'Later Customer')->exists());
        $this->assertDatabaseHas('customers', ['id' => 999, 'name' => 'Their Customer', 'user_id' => $other->id]);
    }

    public function test_merge_keeps_local_records_and_adds_the_archived_ones(): void
    {
        Storage::fake('local');

        $this->actingUser();
        $this->seedShop();
        $contents = $this->archive();

        // A record that only exists here, and the original gone.
        Customer::create(['name' => 'Local Only', 'phone' => '0700000021']);
        DB::table('customers')->where('name', 'Zarghuna')->delete();

        $this->restore($contents, 'merge')->assertSessionHas('success');

        $this->assertTrue(Customer::where('name', 'Local Only')->exists());
        $this->assertTrue(Customer::where('name', 'Zarghuna')->exists());
        $this->assertSame(1, Order::count());
    }

    public function test_a_json_file_that_is_not_an_archive_is_refused_without_touching_the_shop(): void
    {
        Storage::fake('local');

        $this->actingUser();
        $this->seedShop();

        // A file that parses but names none of our tables must never reach the
        // import: in replace mode that would be a wipe driven by a stray file.
        $this->post('/backup/restore', [
            'archive' => UploadedFile::fake()->createWithContent('notes.json', '{"hello":"world"}'),
        ])->assertRedirect(route('backup.restore'))->assertSessionHas('error');

        $this->assertSame(1, Customer::count());
        $this->assertSame([], Storage::disk('local')->files('backups/restore'));
    }

    public function test_a_hand_edited_file_cannot_overwrite_another_account_row(): void
    {
        Storage::fake('local');

        $user = $this->actingUser();
        $other = User::create(['name' => 'Other Shop', 'email' => 'other@test.dev', 'password' => bcrypt('x')]);

        DB::table('customers')->insert([
            'id' => 1,
            'user_id' => $other->id,
            'name' => 'Their Customer',
            'phone' => '0700000030',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $handEdited = (string) json_encode([
            'generated_at' => now()->toDateTimeString(),
            'user_id' => $other->id,
            'customers' => [
                ['id' => 1, 'user_id' => $other->id, 'name' => 'Hijacked', 'phone' => '0700000031'],
                ['id' => 2, 'user_id' => $other->id, 'name' => 'Mine Now', 'phone' => '0700000032'],
            ],
        ]);

        $this->restore($handEdited, 'merge')->assertSessionHas('success');

        // The other account's row is untouched...
        $this->assertDatabaseHas('customers', ['id' => 1, 'name' => 'Their Customer']);

        // ...while the row that did import belongs to the account restoring it.
        $this->assertDatabaseHas('customers', ['id' => 2, 'name' => 'Mine Now', 'user_id' => $user->id]);
    }

    public function test_children_without_their_parent_in_the_file_are_skipped(): void
    {
        Storage::fake('local');

        $this->actingUser();

        // order_items point at an order that is not in the file: writing them
        // would dangle, and SQLite runs with foreign keys on.
        $handEdited = (string) json_encode([
            'generated_at' => now()->toDateTimeString(),
            'customers' => [[
                'id' => 1, 'name' => 'Kept', 'phone' => '0700000040', 'address' => null,
                'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            ]],
            'order_items' => [[
                'id' => 1, 'order_id' => 77, 'product_id' => 88,
                'quantity' => 1, 'unit_price' => '1.00', 'subtotal' => '1.00',
            ]],
        ]);

        $this->restore($handEdited)->assertSessionHas('success');

        $this->assertSame(1, DB::table('customers')->count());
        $this->assertSame(0, DB::table('order_items')->count());
    }

    public function test_picking_from_phone_storage_is_refused_without_the_native_picker(): void
    {
        $this->actingUser();

        // In a browser (and in these tests) there is no bridge to call, so the
        // route says so instead of pretending a file arrived.
        $this->post(route('backup.restore.device'))
            ->assertRedirect(route('backup.restore'))
            ->assertSessionHas('error');
    }

    public function test_the_backup_index_offers_restore(): void
    {
        $this->actingUser();

        $this->get('/backup')
            ->assertOk()
            ->assertSee(route('backup.restore'), false);
    }

    public function test_the_json_export_falls_back_to_a_download_in_the_browser(): void
    {
        Storage::fake('local');

        $user = $this->actingUser();
        Storage::disk('local')->put("backups/user_{$user->id}/backup_2026_09_05_11_24_44.json", '{"customers":[]}');

        // On the phone this lands in Downloads/MGS through the bridge; the
        // browser keeps the plain attachment download.
        $response = $this->get(route('backup.json', 'backup_2026_09_05_11_24_44.json'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $this->assertSame('{"customers":[]}', $response->getContent());
    }
}
