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
use App\Services\Backup\BackupRestoreService;
use App\Support\NativeDocument;
use App\Support\NativePicker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BackupController extends Controller
{
    /**
     * Backups live in a per-user folder so shops never see each other's archives.
     * One backup is a pair of files sharing a name: a rendered PDF, which is
     * what the shop reads and shares, and the machine-readable JSON copy a
     * restore reads back. Both are written on every creation and both are
     * saved to the phone on device.
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
                $hasJson = $disk->exists("{$userDir}/{$base}.json");
                $download = $hasPdf ? "{$base}.pdf" : "{$base}.json";

                $backups[$base] = [
                    'filename' => $filename,
                    'download' => $download,
                    // The restorable twin, offered as its own action on the
                    // phone: the JSON never leaves the app otherwise.
                    'json' => $base.'.json',
                    // Every new backup writes both artifacts, so the row can
                    // show the shop the real pair it owns rather than implying
                    // the PDF is the only file.
                    'has_json' => $hasJson,
                    // Newer backups always have a PDF twin; migrated legacy
                    // archives may be JSON-only but stay downloadable.
                    'is_json_only' => ! $hasPdf,
                    // The row stands for the whole archive, so add up every
                    // half that exists — reporting only the PDF would understate
                    // a new backup by the size of the file it can be restored
                    // from.
                    'size' => $this->humanSize(
                        ($hasPdf ? $disk->size("{$userDir}/{$base}.pdf") : 0)
                        + ($hasJson ? $disk->size("{$userDir}/{$base}.json") : 0)
                    ),
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
        try {
            $pdfBinary = $pdfService->generate($backupData);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('backup.index')
                ->with('error', __('messages.backup_failed'));
        }

        $filename = 'backup_'.date('Y_m_d_H_i_s');
        $jsonContent = json_encode(
            ['generated_at' => now()->toDateTimeString(), 'user_id' => Auth::id()] + $backupData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        $dir = $this->userDir();
        Storage::disk('local')->put("{$dir}/{$filename}.json", $jsonContent);
        Storage::disk('local')->put("{$dir}/{$filename}.pdf", $pdfBinary);

        // On device the WebView can't download; hand the fresh archive to
        // the system viewer / share sheet so the user can save it out.
        if (NativeDocument::available()) {
            // Keep a real copy of BOTH artifacts in Downloads/MGS, so one tap
            // at creation is enough: the PDF is what the shop reads, and the
            // JSON twin is what a restore reads back — the phone's file picker
            // cannot see the app's private storage, where the JSON otherwise
            // stays. The index rows are the retry path when a save is refused.
            $saved = [
                NativeDocument::save($pdfBinary, $filename.'.pdf', 'application/pdf')['ok'],
                NativeDocument::save($jsonContent, $filename.'.json', 'application/json')['ok'],
            ];

            if (in_array(false, $saved, true)) {
                // Storage permission on Android 9 and older: the other artifact
                // still works, and the index has a per-row export to retry with.
                session()->flash('error', __('messages.backup_export_failed'));
            }

            NativeDocument::open($pdfBinary, $filename.'.pdf', 'application/pdf', $filename);
        }

        return redirect()->route('backup.index')
            ->with('success', __('messages.backup_created').' '.__('messages.backup_created_files', [
                'pdf' => $filename.'.pdf',
                'json' => $filename.'.json',
            ]));
    }

    /**
     * Device path for getting an existing archive out of the app: push the
     * stored PDF through the bridge to the system viewer / share sheet
     * (the WebView can't render PDFs or handle attachment responses).
     * The browser keeps the plain attachment download.
     */
    public function share($filename)
    {
        if (! $this->safeFilename($filename) || ! str_ends_with($filename, '.pdf')) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $disk = Storage::disk('local');
        $path = $this->userDir().'/'.$filename;

        if (! $disk->exists($path)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $binary = $disk->get($path);

        if (! NativeDocument::open($binary, $filename, 'application/pdf', $filename)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        return back();
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

    /**
     * Restore entry point. On the phone the archive is picked with the system
     * document picker (the WebView cannot upload a file — see NativePicker);
     * in the browser it is a plain file input.
     */
    public function restore()
    {
        return view('backup.restore', [
            'isDevice' => NativeDocument::available(),
        ]);
    }

    /**
     * Phone path: ask the native shell for a file off the phone's storage and
     * stage it for the confirmation step.
     */
    public function restoreFromDevice()
    {
        if (! NativeDocument::available()) {
            return redirect()->route('backup.restore')->with('error', __('messages.restore_device_only'));
        }

        $picked = NativePicker::pick('application/json');

        // Backing out of the picker is not a failure: the page just stays as it
        // was (the layout only renders success/error banners, and neither fits).
        if ($picked['cancelled']) {
            return redirect()->route('backup.restore');
        }

        if (! $picked['ok'] || ! is_string($picked['data'])) {
            return redirect()->route('backup.restore')->with('error', $this->pickerError($picked['error']));
        }

        return $this->stageArchive(
            (string) base64_decode($picked['data'], true),
            (string) ($picked['filename'] ?: 'backup.json'),
        );
    }

    /**
     * Browser path: a normal multipart upload. The APK never reaches this
     * route — its request bodies arrive as strings, so $_FILES stays empty.
     */
    public function restoreUpload(Request $request)
    {
        $request->validate([
            // Sized in kilobytes: 32768 KB === NativePicker::MAX_BYTES.
            'archive' => ['required', 'file', 'max:32768'],
        ]);

        $file = $request->file('archive');

        return $this->stageArchive(
            (string) file_get_contents($file->getRealPath()),
            (string) $file->getClientOriginalName(),
        );
    }

    /**
     * Device path for turning an archive that is already listed into a file
     * the shop owns: the JSON twin is what a restore reads, and it normally
     * never leaves the app's private storage. The browser keeps the plain
     * attachment download.
     */
    public function saveJson($filename)
    {
        if (! $this->safeFilename($filename) || ! str_ends_with($filename, '.json')) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $disk = Storage::disk('local');
        $path = $this->userDir().'/'.$filename;

        if (! $disk->exists($path)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        if (! NativeDocument::available()) {
            return $this->download($filename);
        }

        if (! NativeDocument::save($disk->get($path), $filename, 'application/json')['ok']) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_export_failed'));
        }

        return back()->with('success', __('messages.backup_exported'));
    }

    /**
     * A validated archive is staged, not imported: a restore can wipe a shop,
     * so the shop gets to see what the file holds first. The staged copy is
     * deleted as soon as it has been applied (or replaced by the next pick).
     */
    protected function stageArchive(string $contents, string $filename)
    {
        if ($contents === '' || strlen($contents) > NativePicker::MAX_BYTES) {
            return redirect()->route('backup.restore')->with('error', __('messages.restore_invalid'));
        }

        try {
            app(BackupRestoreService::class)->inspect($contents);
        } catch (RuntimeException $e) {
            return redirect()->route('backup.restore')->with('error', $this->restoreError($e->getMessage()));
        }

        $this->forgetStaleArchives();

        $token = Str::random(40);
        Storage::disk('local')->put($this->pendingPath($token), $contents);

        // The token travels in the URL rather than in the session: the flow then
        // survives a WebView that drops cookies mid-journey, and a token cannot
        // be guessed (40 random characters) or used on someone else's file.
        return redirect()->route('backup.restore.preview', [
            'token' => $token,
            // Caption only, clipped to a sane length.
            'name' => mb_substr(basename($filename), 0, 100),
        ]);
    }

    /**
     * The confirmation screen: what the staged archive holds, and how to apply
     * it. Reached by redirect, so a refresh cannot re-upload the file.
     */
    public function restorePreview(Request $request)
    {
        $path = $this->stagedArchivePath((string) $request->query('token', ''));

        if ($path === null) {
            return redirect()->route('backup.restore')->with('error', __('messages.restore_no_file'));
        }

        try {
            $preview = app(BackupRestoreService::class)->inspect(Storage::disk('local')->get($path));
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);

            return redirect()->route('backup.restore')->with('error', $this->restoreError($e->getMessage()));
        }

        return view('backup.restore-preview', [
            'token' => (string) $request->query('token', ''),
            'filename' => (string) ($request->query('name') ?: 'backup.json'),
            'preview' => $preview,
        ]);
    }

    /**
     * Apply the staged archive. Replace is the default because that is what
     * "restore" means: the shop goes back to the state in the file.
     */
    public function applyRestore(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{40}$/'],
            'mode' => ['required', 'in:'.BackupRestoreService::MODE_REPLACE.','.BackupRestoreService::MODE_MERGE],
        ]);

        $path = $this->stagedArchivePath($validated['token']);

        if ($path === null) {
            return redirect()->route('backup.restore')->with('error', __('messages.restore_no_file'));
        }

        $contents = Storage::disk('local')->get($path);

        try {
            $result = app(BackupRestoreService::class)->apply($contents, (int) Auth::id(), (string) $validated['mode']);
        } catch (RuntimeException $e) {
            // The import runs in one transaction, so the shop is exactly as it
            // was before this request.
            return redirect()->route('backup.restore')->with('error', $this->restoreError($e->getMessage()));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('backup.restore')->with('error', __('messages.restore_failed'));
        }

        Storage::disk('local')->delete($path);

        return redirect()->route('backup.index')
            ->with('success', __('messages.restore_done', ['count' => number_format($result['total'])]));
    }

    /**
     * Path of the archive waiting for confirmation, or null when the token is
     * not one of ours or its file is already gone.
     */
    protected function stagedArchivePath(string $token): ?string
    {
        $path = $this->pendingPath($token);

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Drop staged archives nobody confirmed. A restore that was picked but
     * never applied would otherwise sit in private storage forever; a day is
     * far longer than the walk from the picker to the confirm button.
     */
    protected function forgetStaleArchives(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->directoryExists('backups/restore')) {
            return;
        }

        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->files('backups/restore') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Where a picked-but-unconfirmed archive waits. Tokens are minted in
     * stageArchive(), so anything that is not one of ours is refused outright
     * rather than pasted into a path.
     */
    protected function pendingPath(string $token): string
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return '';
        }

        return 'backups/restore/'.$token.'.json';
    }

    /** Map the picker's error codes onto something a shopkeeper can act on. */
    protected function pickerError(?string $error): string
    {
        return match ($error) {
            'file_too_large' => __('messages.restore_too_large'),
            'picker_timeout', 'no_activity', 'unreadable_file' => __('messages.restore_pick_failed'),
            default => __('messages.restore_pick_failed'),
        };
    }

    /** Map the restore service's refusal codes onto messages. */
    protected function restoreError(string $code): string
    {
        return match ($code) {
            'backup_unreadable', 'backup_not_recognised' => __('messages.restore_invalid'),
            'backup_empty' => __('messages.restore_empty'),
            default => __('messages.restore_failed'),
        };
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
