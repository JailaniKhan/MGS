<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Expense;
use App\Models\Employee;
use App\Models\CashbookEntry;
use App\Models\Category;
use App\Models\Unit;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class BackupController extends Controller
{
    public function index()
    {
        $backupPath = storage_path('app/private/backups');
        $backups = [];
        
        if (is_dir($backupPath)) {
            $files = scandir($backupPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $backups[] = [
                        'filename' => $file,
                        'size' => number_format(filesize($backupPath . '/' . $file) / 1024, 2) . ' KB',
                        'date' => date('Y/m/d H:i', filemtime($backupPath . '/' . $file))
                    ];
                }
            }
            usort($backups, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }
        
        return view('backup.index', compact('backups'));
    }

    public function create()
    {
        return view('backup.create');
    }

    public function store(Request $request)
    {
        $userId = Auth::id();

        $backupData = [
            'customers' => Customer::where('user_id', $userId)->get()->toArray(),
            'suppliers' => Supplier::where('user_id', $userId)->get()->toArray(),
            'products' => Product::where('user_id', $userId)->get()->toArray(),
            'orders' => Order::where('user_id', $userId)->with('orderItems')->get()->toArray(),
            'order_items' => OrderItem::whereHas('order', fn($q) => $q->where('user_id', $userId))->get()->toArray(),
            'payments' => Payment::whereHas('order', fn($q) => $q->where('user_id', $userId))->get()->toArray(),
            'purchases' => Purchase::where('user_id', $userId)->with('purchaseItems')->get()->toArray(),
            'purchase_items' => PurchaseItem::whereHas('purchase', fn($q) => $q->where('user_id', $userId))->get()->toArray(),
            'purchase_payments' => PurchasePayment::whereHas('purchase', fn($q) => $q->where('user_id', $userId))->get()->toArray(),
            'expenses' => Expense::where('user_id', $userId)->get()->toArray(),
            'employees' => Employee::where('user_id', $userId)->get()->toArray(),
            'cashbook_entries' => CashbookEntry::where('user_id', $userId)->get()->toArray(),
            'categories' => Category::where('user_id', $userId)->get()->toArray(),
            'units' => Unit::where('user_id', $userId)->get()->toArray(),
            'order_returns' => OrderReturn::where('user_id', $userId)->with('items')->get()->toArray(),
            'order_return_items' => OrderReturnItem::whereHas('orderReturn', fn($q) => $q->where('user_id', $userId))->get()->toArray(),
            'purchase_returns' => PurchaseReturn::where('user_id', $userId)->with('items')->get()->toArray(),
            'purchase_return_items' => PurchaseReturnItem::whereHas('purchaseReturn', fn($q) => $q->where('user_id', $userId))->get()->toArray(),
            'stock_movements' => StockMovement::where('user_id', $userId)->get()->toArray(),
            'settings' => Setting::all()->toArray(),
        ];

        $filename = 'backup_' . date('Y_m_d_H_i_s') . '.json';
        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        Storage::disk('local')->put('backups/' . $filename, $jsonContent);

        return redirect()->route('backup.index')->with('success', __('messages.backup_created') . ' ' . __('messages.file_name') . ': ' . $filename);
    }

    public function download($filename)
    {
        $filePath = 'backups/' . $filename;
        
        if (!Storage::disk('local')->exists($filePath)) {
            return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
        }

        $fileContent = Storage::disk('local')->get($filePath);
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response($fileContent, 200, $headers);
    }

    public function destroy($filename)
    {
        $filePath = 'backups/' . $filename;
        
        if (Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
            return redirect()->route('backup.index')->with('success', __('messages.backup_deleted'));
        }

        return redirect()->route('backup.index')->with('error', __('messages.backup_file_name'));
    }
}