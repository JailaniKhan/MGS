<?php

namespace Tests\Feature;

use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Purchase;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\Accounting\TransactionService;
use App\Services\Backup\BackupPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_renders_party_names_across_all_sections(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'B', 'email' => 'bs@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Zarghuna', 'phone' => '0700000009']);
        $supplier = Supplier::create(['name' => 'Safi Wholesaler', 'phone' => '0700000010']);

        Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '100.00',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '200.00',
            'total_amount' => '200.00',
            'currency' => 'USD',
        ]);

        PartyPayment::create([
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'amount' => '50.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
            'notes' => 'partial',
        ]);

        // Orders, purchases and the ledger all embed party names as table
        // cells; before the fix partyNames() returned full row arrays and
        // this request died with "Array to string conversion".
        $response = $this->post('/backup');
        $response->assertRedirect(route('backup.index'))
            ->assertSessionHas('success');

        $disk = Storage::disk('local');
        $files = collect($disk->files('backups/user_'.$user->id));
        $this->assertTrue($files->contains(fn ($f) => str_ends_with($f, '.json')));
        $this->assertTrue($files->contains(fn ($f) => str_ends_with($f, '.pdf')));
    }

    public function test_pdf_embeds_arabic_font_and_keeps_phones_intact(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'B2', 'email' => 'bs2@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        app()->setLocale('ps');

        $customer = Customer::create([
            'name' => 'زارغونه',
            'phone' => '0700268836',   // leading zero must survive
            'address' => 'کابل',
        ]);

        $this->post('/backup')->assertRedirect(route('backup.index'));

        $disk = Storage::disk('local');
        $pdf = collect($disk->files('backups/user_'.$user->id))
            ->first(fn ($f) => str_ends_with($f, '.pdf'));
        $this->assertNotNull($pdf, 'PDF artifact missing');

        $binary = $disk->get($pdf);

        // Lateef is embedded (mPDF may prefix subset names, so check the
        // family loosely) — DejaVu alone would render Pashto as tofu, and
        // modern Noto Naskh builds can't be parsed by mPDF 8.x at all.
        $this->assertStringContainsStringIgnoringCase('Lateef', $binary);

        // No escaped money/date spans leak as literal text.
        $this->assertStringNotContainsString('&lt;span', $binary);
    }

    public function test_identifier_columns_keep_phone_digits_ungrouped(): void
    {
        // Phones live in the PDF's FlateDecode-compressed content streams
        // as glyph indices, so "intact" can't be asserted on raw bytes —
        // pin it at the seam instead: txt() must never group digits like
        // num() does, or "0700268836" would render as "700,268,836".
        $service = new BackupPdfService;
        $txt = new \ReflectionMethod($service, 'txt');
        $txt->setAccessible(true);
        $num = new \ReflectionMethod($service, 'num');
        $num->setAccessible(true);

        $this->assertSame('0700268836', $txt->invoke($service, '0700268836'));
        $this->assertNotSame('0700268836', $num->invoke($service, '0700268836'));
    }

    public function test_store_writes_no_files_when_pdf_render_fails(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'B3', 'email' => 'bs3@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        Customer::create(['name' => 'Orphan Guard', 'phone' => '0700000011']);

        // A render failure must leave zero artifacts: the PDF is rendered
        // before anything is written, so no orphan JSON from a later crash.
        $pdf = $this->mock(BackupPdfService::class);
        $pdf->shouldReceive('generate')->andThrow(new \RuntimeException('render boom'));
        $this->instance(BackupPdfService::class, $pdf);

        $this->post('/backup');

        $files = Storage::disk('local')->files('backups/user_'.$user->id);
        $this->assertSame([], $files, 'no archive files should exist after a failed render');
    }

    public function test_backup_json_contains_both_cashbook_systems_and_no_reminders(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'B4', 'email' => 'bs4@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        app(ChartOfAccountsSeeder::class)->seedForUser($user);

        // Legacy generation: a plain cashbook_entries row.
        CashbookEntry::create([
            'type' => 'out',
            'amount' => '150.00',
            'currency' => 'AFN',
            'category' => 'rent',
            'notes' => 'legacy row',
            'entry_date' => '2026-08-20',
        ]);

        // New generation: journal-based entry with its ledger lines.
        app(TransactionService::class)->postCashbookEntry(
            $user,
            'in',
            '300.00',
            'USD',
            ['description' => 'Cash income', 'transaction_date' => '2026-08-25']
        );

        // Reminders are explicitly excluded from the archive.
        Reminder::create([
            'remindable_type' => 'customer',
            'remindable_id' => 1,
            'amount' => '10.00',
            'currency' => 'AFN',
            'channel' => 'whatsapp',
            'message' => 'pay up',
            'status' => 'sent',
        ]);

        $this->post('/backup')->assertRedirect(route('backup.index'));

        $disk = Storage::disk('local');
        $json = collect($disk->files('backups/user_'.$user->id))
            ->first(fn ($f) => str_ends_with($f, '.json'));
        $this->assertNotNull($json, 'JSON artifact missing');

        $payload = json_decode($disk->get($json), true);

        // Both cashbook generations are machine-readable, ledger lines included.
        $this->assertNotEmpty($payload['cashbook_entries']);
        $this->assertNotEmpty($payload['journal_entries']);
        $this->assertNotEmpty($payload['ledger_entries']);
        $this->assertSame('300.00', collect($payload['ledger_entries'])->max('amount'));

        // Reminders are gone from the archive entirely.
        $this->assertArrayNotHasKey('reminders', $payload);
    }

    public function test_pdf_cashbook_section_merges_legacy_and_journal_rows(): void
    {
        // The PDF's content streams are FlateDecode-compressed, so the merge
        // is asserted at the sections() seam with synthetic backup data: one
        // legacy row and one journal entry must both appear, newest first.
        $service = new BackupPdfService;
        $sections = new \ReflectionMethod($service, 'sections');
        $sections->setAccessible(true);

        $data = [
            'customers' => [['id' => 7, 'name' => 'Zarghuna']],
            'suppliers' => [],
            'cashbook_entries' => [[
                'id' => 1,
                'type' => 'out',
                'amount' => '150.00',
                'currency' => 'AFN',
                'category' => 'rent',
                'notes' => 'legacy row',
                'entry_date' => '2026-08-20',
                'created_at' => '2026-08-20 10:00:00',
            ]],
            'journal_entries' => [[
                'id' => 9,
                'source' => 'cashbook_in',
                'currency' => 'USD',
                'description' => 'Cash income',
                'transaction_date' => '2026-08-25',
                'created_at' => '2026-08-25 09:00:00',
                'reference_type' => 'customer',
                'reference_id' => 7,
            ]],
            'ledger_entries' => [
                ['id' => 1, 'journal_entry_id' => 9, 'amount' => '300.00', 'notes' => 'paid by card'],
                ['id' => 2, 'journal_entry_id' => 9, 'amount' => '300.00', 'notes' => null],
            ],
        ];

        $list = $sections->invoke($service, $data);

        // No reminders section exists anymore.
        $this->assertNotContains(__('messages.reminders'), array_column($list, 'title'));

        $cashbook = collect($list)->first(fn ($s) => $s['title'] === __('messages.cashbook_section'));
        $this->assertNotNull($cashbook, 'cashbook section missing');

        $rows = ($cashbook['rows'])($data);

        // Newest first: the journal entry (08-25) above the legacy row (08-20).
        $this->assertCount(2, $rows);
        $this->assertSame('in', $rows[0][0]);
        $this->assertStringContainsString('Zarghuna', (string) $rows[0][1]);
        $this->assertStringContainsString('300.00', (string) $rows[0][2]);
        $this->assertStringContainsString('paid by card', (string) $rows[0][4]);

        $this->assertSame('out', $rows[1][0]);
        $this->assertStringContainsString('150.00', (string) $rows[1][2]);
    }

    public function test_create_page_shows_merged_cashbook_tile(): void
    {
        $user = User::create(['name' => 'B5', 'email' => 'bs5@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        // The create page's tile count covers BOTH cashbook generations.
        CashbookEntry::create([
            'type' => 'in',
            'amount' => '10.00',
            'currency' => 'AFN',
            'category' => 'sales',
            'entry_date' => '2026-08-20',
        ]);
        app(ChartOfAccountsSeeder::class)->seedForUser($user);
        app(TransactionService::class)->postCashbookEntry($user, 'out', '20.00', 'AFN');

        $this->get('/backup/create')
            ->assertOk()
            ->assertSee(__('messages.cashbook'))
            ->assertSee(number_format(2));
    }
}
