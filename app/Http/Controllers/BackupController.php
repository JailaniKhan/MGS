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
        $backupData = [
            'customers' => Customer::all()->toArray(),
            'suppliers' => Supplier::all()->toArray(),
            'products' => Product::all()->toArray(),
            'orders' => Order::with('orderItems')->get()->toArray(),
            'order_items' => OrderItem::all()->toArray(),
            'payments' => Payment::all()->toArray(),
            'purchases' => Purchase::with('purchaseItems')->get()->toArray(),
            'purchase_items' => PurchaseItem::all()->toArray(),
            'purchase_payments' => PurchasePayment::all()->toArray(),
            'expenses' => Expense::all()->toArray(),
            'employees' => Employee::all()->toArray(),
            'cashbook_entries' => CashbookEntry::all()->toArray(),
            'categories' => Category::all()->toArray(),
            'units' => Unit::all()->toArray(),
            'order_returns' => OrderReturn::with('items')->get()->toArray(),
            'order_return_items' => OrderReturnItem::all()->toArray(),
            'purchase_returns' => PurchaseReturn::with('items')->get()->toArray(),
            'purchase_return_items' => PurchaseReturnItem::all()->toArray(),
            'stock_movements' => StockMovement::all()->toArray(),
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