<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\CashbookEntry;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\SalaryPayment;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Backup\BackupPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    /**
     * Backups live in a per-user folder so shops never see each other's archives.
     * The canonical machine-readable copy stays JSON (future restore support);
     * the downloadable artifact is a rendered PDF, which is what users share.
     */
    public function index()
    {
        $disk = Storage::disk('local');
        $userDir = $this->userDir();

        // Pre-PDF archives once lived in a shared root any user could read.
        // Fold them into per-user folders before listing anything.
        $this->migrateLegacyBackups();

        $backups = [];
        if ($disk->directoryExists($userDir)) {
            foreach ($disk->files($userDir) as $file) {
                $filename = basename($file);
                if (! $this->safeFilename($filename)) {
                    continue;
                }
                $base = $this->stripExtension($filename);
                $hasPdf = $disk->exists("{$userDir}/{$base}.pdf");
                $download = $hasPdf ? "{$base}.pdf" : "{$base}.json";

                $backups[$base] = [
                    'filename' => $filename,
                    'download' => $download,
                    // Newer backups always have a PDF twin; migrated legacy
                    // archives may be JSON-only but stay downloadable.
                    'is_json_only' => ! $hasPdf,
                    'size' => $this->humanSize($disk->size("{$userDir}/{$download}")),
                    'date' => date('Y/m/d H:i', $disk->lastModified($file)),
                ];
            }
            krsort($backups);
        }

        return view('backup.index', compact('backups'));
    }

    /**
     * One-time lazy migration: move pre-PDF archives out of the shared
     * "backups" root into their owner's per-user folder. Ownership comes
     * from the JSON body's user_id; archives older than that convention
     * belong to the oldest account (device-local installs have exactly one).
     */
    protected function migrateLegacyBackups(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->directoryExists('backups')) {
            return;
        }

        $legacyFiles = array_filter(
            $disk->files('backups'),
            fn ($file) => str_ends_with($file, '.json') && $this->safeFilename(basename($file))
        );

        if ($legacyFiles === []) {
            return;
        }

        $fallbackUserId = User::query()->oldest('id')->value('id');
        if (! $fallbackUserId) {
            return;
        }

        foreach ($legacyFiles as $file) {
            $payload = json_decode($disk->get($file), true);
            $userId = (int) ($payload['user_id'] ?? 0);

            if (! User::query()->whereKey($userId)->exists()) {
                $userId = $fallbackUserId;
            }

            $target = "backups/user_{$userId}/".basename($file);
            $disk->put($target, $disk->get($file));
            $disk->delete($file);
        }
    }

    public function create()
    {
        $counts = $this->recordCounts();

        return view('backup.create', compact('counts'));
    }

    public function store(Request $request, BackupPdfService $pdfService)
    {
        $backupData = $this->gatherBackupData();

        // Render first: if the PDF blows up (bad data, font issue), no
        // half-written archive is left on disk. Only when both artifacts
        // are built in memory do we write them to storage.
        $pdfBinary = $pdfService->generate($backupData);

        $filename = 'backup_'.date('Y_m_d_H_i_s');
        $jsonContent = json_encode(
            ['generated_at' => now()->toDateTimeString(), 'user_id' => Auth::id()] + $backupData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        $dir = $this->userDir();
        Storage::disk('local')->put("{$dir}/{$filename}.json", $jsonContent);
        Storage::disk('local')->put("{$dir}/{$filename}.pdf", $pdfBinary);

        return redirect()->route('backup.index')
            ->with('success', __('messages.backup_created').' '.__('messages.file_name').': '.$filename.'.pdf');
    }

    public function download($filename)
    {
        if (! $this->safeFilename($filename)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $disk = Storage::disk('local');
        $path = $this->userDir().'/'.$filename;

        if (! $disk->exists($path)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $mime = str_ends_with($filename, '.json') ? 'application/json' : 'application/pdf';

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function destroy($filename)
    {
        if (! $this->safeFilename($filename)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $disk = Storage::disk('local');
        $base = $this->stripExtension($filename);
        $pdfPath = $this->userDir().'/'.$base.'.pdf';
        $jsonPath = $this->userDir().'/'.$base.'.json';

        if ($disk->exists($pdfPath) || $disk->exists($jsonPath)) {
            $disk->delete(array_filter([$pdfPath, $jsonPath]));

            return redirect()->route('backup.index')->with('success', __('messages.backup_deleted'));
        }

        return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
    }

    protected function userDir(): string
    {
        return 'backups/user_'.Auth::id();
    }

    /**
     * Filenames must be ours (backup_YYYY_MM_DD_HH_MM_SS plus extension) and
     * never carry path separators or dots beyond the extension.
     */
    protected function safeFilename(string $filename): bool
    {
        return (bool) preg_match('/^backup_[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{2}_[0-9]{2}_[0-9]{2}\.(pdf|json)$/', $filename);
    }

    /**
     * Strip the real extension (.pdf is 4 chars but .json is 5 — a blind
     * substr(-4) leaves a trailing dot and builds "..json" paths).
     */
    protected function stripExtension(string $filename): string
    {
        return (string) preg_replace('/\.(pdf|json)$/', '', $filename);
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format(max($bytes, 0) / 1024, 2).' KB';
    }

    protected function recordCounts(): array
    {
        return [
            'customers' => Customer::count(),
            'suppliers' => Supplier::count(),
            'products' => Product::count(),
            'orders' => Order::count(),
            'purchases' => Purchase::count(),
            'payments' => Payment::count() + PurchasePayment::count() + PartyPayment::count(),
            'expenses' => Expense::count(),
            'staff' => Employee::count(),
            // Both cashbook generations: legacy cashbook_entries rows plus
            // journal-based entries (the only write path since the redesign).
            'cashbook' => CashbookEntry::count()
                + JournalEntry::whereIn('source', ['cashbook_in', 'cashbook_out'])->count(),
        ];
    }

    /**
     * Every table the shop owns. Child rows without their own user_id are
     * pulled through their parent, mirroring the global model scopes.
     */
    protected function gatherBackupData(): array
    {
        $orderIds = Order::pluck('id');
        $purchaseIds = Purchase::pluck('id');
        $employeeIds = Employee::pluck('id');
        $journalIds = JournalEntry::pluck('id');

        // Child rows carry display names/currency for the PDF rendering step;
        // the JSON archive stays the raw machine-readable copy.
        $productNames = Product::pluck('name', 'id');
        $orderCurrencies = Order::pluck('currency', 'id');
        $purchaseCurrencies = Purchase::pluck('currency', 'id');

        $orderItems = OrderItem::whereIn('order_id', $orderIds)->get()->map(function ($item) use ($productNames, $orderCurrencies) {
            $row = $item->toArray();
            $row['product_name'] = $productNames[$item->product_id] ?? null;
            $row['order_currency'] = $orderCurrencies[$item->order_id] ?? 'AFN';

            return $row;
        })->all();

        $purchaseItems = PurchaseItem::whereIn('purchase_id', $purchaseIds)->get()->map(function ($item) use ($productNames, $purchaseCurrencies) {
            $row = $item->toArray();
            $row['product_name'] = $productNames[$item->product_id] ?? null;
            $row['purchase_currency'] = $purchaseCurrencies[$item->purchase_id] ?? 'AFN';

            return $row;
        })->all();

        return [
            'customers' => Customer::get()->toArray(),
            'suppliers' => Supplier::get()->toArray(),
            'categories' => Category::get()->toArray(),
            'units' => Unit::get()->toArray(),
            'products' => Product::get()->toArray(),
            'orders' => Order::get()->toArray(),
            'order_items' => $orderItems,
            'payments' => Payment::whereIn('order_id', $orderIds)->get()->toArray(),
            'order_returns' => OrderReturn::whereIn('order_id', $orderIds)->get()->toArray(),
            'purchases' => Purchase::get()->toArray(),
            'purchase_items' => $purchaseItems,
            'purchase_payments' => PurchasePayment::whereIn('purchase_id', $purchaseIds)->get()->toArray(),
            'purchase_returns' => PurchaseReturn::whereIn('purchase_id', $purchaseIds)->get()->toArray(),
            'party_payments' => PartyPayment::get()->toArray(),
            'expenses' => Expense::get()->toArray(),
            'employees' => Employee::get()->toArray(),
            'salary_payments' => SalaryPayment::whereIn('employee_id', $employeeIds)->get()->toArray(),
            'cashbook_entries' => CashbookEntry::get()->toArray(),
            'accounts' => Account::get()->toArray(),
            'journal_entries' => JournalEntry::get()->toArray(),
            // Journal money lives in its ledger lines; without them the JSON
            // archive can't answer "how much was this cashbook entry?".
            'ledger_entries' => LedgerEntry::whereIn('journal_entry_id', $journalIds)->get()->toArray(),
            'stock_movements' => StockMovement::get()->toArray(),
            'audit_logs' => AuditLog::where('user_id', Auth::id())->get()->toArray(),
            'settings' => Setting::all()->toArray(),
        ];
    }
}
