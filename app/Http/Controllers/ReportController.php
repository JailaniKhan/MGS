<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\PartyPayment;
use App\Models\Account;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\CashbookEntry;
use App\Models\SalaryPayment;
use App\Models\PurchaseItem;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\PurchaseReturn;
use App\Models\Expense;
use App\Services\Accounting\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(
        private BalanceService $balanceService,
    ) {}

    public function profitLoss(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());
        $currency = $request->get('currency', 'AFN');

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Total Revenue from non-cancelled orders (net of returns)
        $totalRevenue = Order::where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('total_amount');
        $orderReturns = OrderReturn::where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('return_date', [$startDateTime, $endDateTime])
            ->sum('total_amount');
        $totalRevenue = bcsub((string) $totalRevenue, (string) $orderReturns, 2);

        // Cost of Goods Sold: cost of inventory actually purchased (net of purchase returns).
        // (Proper per-unit COGS tracking is a future enhancement; this uses purchased cost
        // which is the standard simplification when no sales-level cost is recorded.)
        $purchasesCost = Purchase::where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('total_amount');
        $purchaseReturns = PurchaseReturn::where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('return_date', [$startDateTime, $endDateTime])
            ->sum('total_amount');
        $totalCOGS = bcsub((string) $purchasesCost, (string) $purchaseReturns, 2);

        // Operating expenses
        $cashExpenses = CashbookEntry::where('type', 'out')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('amount');
        $salaryExpenses = SalaryPayment::where('currency', $currency)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('amount');
        // Include the dedicated Expense table (Fix C4)
        $operatingExpenses = Expense::where('currency', $currency)
            ->whereBetween('expense_date', [$startDateTime, $endDateTime])
            ->sum('amount');

        $totalExpenses = bcadd(bcadd((string) $totalCOGS, (string) $cashExpenses, 2), bcadd((string) $salaryExpenses, (string) $operatingExpenses, 2), 2);
        $netProfit = bcsub((string) $totalRevenue, (string) $totalExpenses, 2);

        $selectedCurrency = $currency;

        return view('reports.profit_loss', compact(
            'totalRevenue', 'totalCOGS', 'cashExpenses', 'salaryExpenses', 'operatingExpenses',
            'totalExpenses', 'netProfit', 'startDate', 'endDate', 'selectedCurrency'
        ));
    }

    public function balanceSheet(Request $request)
    {
        $currency = $request->get('currency', 'AFN');

        // ASSETS
        // Cash in hand, derived from the live transaction tables (Fix C1 — the double-entry
        // ledger is not yet populated by the app, so BalanceService returns 0.00).
        $cashIn = bcadd(
            bcadd(
                (string) Payment::where('currency', $currency)->sum('amount'),
                (string) PartyPayment::where('currency', $currency)->where('type', 'payment_received')->sum('amount'),
                2
            ),
            (string) CashbookEntry::where('type', 'in')->where('currency', $currency)->sum('amount'),
            2
        );
        $cashOut = bcadd(
            bcadd(
                bcadd(
                    (string) PurchasePayment::where('currency', $currency)->sum('amount'),
                    (string) PartyPayment::where('currency', $currency)->where('type', 'payment_made')->sum('amount'),
                    2
                ),
                (string) CashbookEntry::where('type', 'out')->where('currency', $currency)->sum('amount'),
                2
            ),
            (string) SalaryPayment::where('currency', $currency)->sum('amount'),
            2
        );
        $cashBalance = bcsub($cashIn, $cashOut, 2);

        // Accounts Receivable (what customers owe us) = sum of order remaining_amount per currency.
        $receivables = '0.00';
        foreach (Order::where('currency', $currency)->where('status', '!=', 'cancelled')->get() as $order) {
            $receivables = bcadd($receivables, (string) $order->remaining_amount, 2);
        }

        // Inventory Value (stock * average purchase price)
        $inventoryValue = 0;
        $products = Product::all();
        foreach ($products as $product) {
            $avgPurchasePrice = PurchaseItem::where('product_id', $product->id)
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->where('purchases.currency', $currency)
                ->where('purchases.status', '!=', 'cancelled')
                ->avg('purchase_items.unit_price') ?? 0;
            $inventoryValue += $product->stock * $avgPurchasePrice;
        }

        $totalAssets = bcadd($cashBalance, $receivables, 2);
        $totalAssets = bcadd($totalAssets, (string) $inventoryValue, 2);

        // LIABILITIES
        // Accounts Payable (what we owe suppliers) = sum of purchase remaining_amount per currency.
        $payables = '0.00';
        foreach (Purchase::where('currency', $currency)->where('status', '!=', 'cancelled')->get() as $purchase) {
            $payables = bcadd($payables, (string) $purchase->remaining_amount, 2);
        }

        // EQUITY
        // Retained Earnings = lifetime revenue (net of returns) - lifetime expenses.
        $totalRevenue = Order::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $orderReturns = OrderReturn::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $totalRevenue = bcsub((string) $totalRevenue, (string) $orderReturns, 2);

        $purchasesCost = Purchase::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $purchaseReturns = PurchaseReturn::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $cogs = bcsub((string) $purchasesCost, (string) $purchaseReturns, 2);
        // COGS nets out ending inventory still on hand; otherwise purchases would be counted
        // both as an expense here and as an asset above (double-counting).
        $cogs = bcsub($cogs, (string) $inventoryValue, 2);

        $operating = bcadd(
            bcadd(
                (string) CashbookEntry::where('type', 'out')->where('currency', $currency)->sum('amount'),
                (string) SalaryPayment::where('currency', $currency)->sum('amount'),
                2
            ),
            (string) Expense::where('currency', $currency)->sum('amount'),
            2
        );
        $totalExpenses = bcadd($cogs, $operating, 2);
        $retainedEarnings = bcsub($totalRevenue, $totalExpenses, 2);

        // Owner's Capital = net unallocated party payments (cash introduced / withdrawn by the owner).
        // These are counted in Cash in Hand above but are not operating profit, so they must sit in equity.
        $ownerCapital = bcsub(
            (string) PartyPayment::where('currency', $currency)->where('type', 'payment_received')->sum('amount'),
            (string) PartyPayment::where('currency', $currency)->where('type', 'payment_made')->sum('amount'),
            2
        );

        $totalEquity = bcadd($retainedEarnings, $ownerCapital, 2);
        $totalLiabilitiesEquity = bcadd($payables, $totalEquity, 2);

        $selectedCurrency = $currency;

        return view('reports.balance_sheet', compact(
            'cashBalance', 'receivables', 'inventoryValue', 'totalAssets',
            'payables', 'retainedEarnings', 'ownerCapital', 'totalLiabilitiesEquity',
            'selectedCurrency'
        ));
    }

    public function stockReport(Request $request)
    {
        $currency = $request->get('currency', 'AFN');
        $minStockThreshold = Setting::get('min_stock_threshold', 10);

        $products = Product::with('category')->get();

        $stockData = $products->map(function ($product) use ($currency, $minStockThreshold) {
            $avgPurchasePrice = PurchaseItem::where('product_id', $product->id)
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->where('purchases.currency', $currency)
                ->where('purchases.status', '!=', 'cancelled')
                ->avg('purchase_items.unit_price') ?? 0;

            $avgSalePrice = OrderItem::where('product_id', $product->id)
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.currency', $currency)
                ->where('orders.status', '!=', 'cancelled')
                ->avg('order_items.unit_price') ?? 0;

            $stockValue = $product->stock * $avgPurchasePrice;

            return [
                'product' => $product,
                'avg_purchase_price' => $avgPurchasePrice,
                'avg_sale_price' => $avgSalePrice,
                'stock_value' => $stockValue,
                'is_low_stock' => $product->stock < $minStockThreshold,
            ];
        });

        $lowStockProducts = $stockData->filter(fn($item) => $item['is_low_stock']);
        $totalStockValue = $stockData->sum('stock_value');

        $selectedCurrency = $currency;

        return view('reports.stock_report', compact(
            'stockData', 'lowStockProducts', 'totalStockValue', 'selectedCurrency', 'minStockThreshold'
        ));
    }

    public function daybook(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $currency = $request->get('currency', 'AFN');
        $selectedCurrency = $currency;

        $transactions = collect();

        // Sales (completed orders) — recorded as a non-cash accrual line so they appear in the
        // daybook for reference, but do NOT inflate the cash running balance (Fix M4).
        $sales = Order::with('customer')
            ->where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($order) {
                return [
                    'date' => $order->created_at->toDateString(),
                    'type' => 'sale',
                    'description' => __('messages.sales_dash') . optional($order->customer)->name,
                    'in_amount' => 0,
                    'out_amount' => 0,
                    'created_at' => $order->created_at,
                ];
            });

        // Purchase payments (money going out for purchases)
        $purchasePayments = PurchasePayment::with('purchase.supplier')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->created_at->toDateString(),
                    'type' => 'purchase_payment',
                    'description' => __('messages.pending_purchase_dash') . optional($payment->purchase)->party?->name,
                    'in_amount' => 0,
                    'out_amount' => (float) $payment->amount,
                    'created_at' => $payment->created_at,
                ];
            });

        // Customer payments (money coming in)
        $customerPayments = Payment::with('order.customer')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->created_at->toDateString(),
                    'type' => 'customer_payment',
                    'description' => __('messages.supplier_delivery_dash') . optional($payment->order->customer)->name,
                    'in_amount' => (float) $payment->amount,
                    'out_amount' => 0,
                    'created_at' => $payment->created_at,
                ];
            });

        // Cash entries
        $cashEntries = CashbookEntry::where('currency', $currency)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->get()
            ->map(function ($entry) {
                return [
                    'date' => $entry->entry_date,
                    'type' => 'cash_' . $entry->type,
                    'description' => __('messages.cashbook_section') . ': ' . ($entry->type === 'in' ? __('messages.income') : __('messages.expense_out')) . ($entry->notes ? ' - ' . $entry->notes : ''),
                    'in_amount' => $entry->type === 'in' ? (float) $entry->amount : 0,
                    'out_amount' => $entry->type === 'out' ? (float) $entry->amount : 0,
                    'created_at' => $entry->created_at,
                ];
            });

        // Ledger entries (manual PartyPayment adjustments). These are kept for visibility but,
        // to avoid double-counting supplier/customer payments already captured in the structured
        // Payment / PurchasePayment tables (Fix M3), we only include ledger entries that are NOT
        // already represented by those tables. Since PartyPayment has no link back, and the
        // structured tables are the system of record for document-linked cash, we OMIT the
        // PartyPayment merge here and rely on Payment + PurchasePayment for cash movements.
        // (Manual PartyPayment rows remain visible in the dedicated Ledger screens.)

        $transactions = $transactions->merge($sales)
            ->merge($purchasePayments)
            ->merge($customerPayments)
            ->merge($cashEntries)
            ->sortByDesc('created_at');

        // Group by date and calculate running balance
        // NOTE: BalanceService::cashBalance only accepts 2 args (current balance,
        // not date-filtered). Drop the third arg to avoid ArgumentCountError.
        $openingBalance = $this->balanceService->cashBalance(Auth::id(), $currency);
        $runningBalance = (float) $openingBalance;
        $dailyTotals = [];

        foreach ($transactions as $tx) {
            $date = $tx['date'];
            if (!isset($dailyTotals[$date])) {
                $dailyTotals[$date] = [
                    'date' => $date,
                    'in_total' => 0,
                    'out_total' => 0,
                    'transactions' => [],
                ];
            }
            $dailyTotals[$date]['transactions'][] = $tx;
            $dailyTotals[$date]['in_total'] += $tx['in_amount'];
            $dailyTotals[$date]['out_total'] += $tx['out_amount'];
        }

        // Sort by date descending and calculate running balance
        $dailyTotals = collect($dailyTotals)->sortByDesc('date')->values();

        $runningBalance = (float) $openingBalance;
        $dailyTotals = $dailyTotals->map(function ($day) use (&$runningBalance) {
            $runningBalance += $day['in_total'] - $day['out_total'];
            $day['running_balance'] = $runningBalance;
            return $day;
        });

        $totalIn = $transactions->sum('in_amount');
        $totalOut = $transactions->sum('out_amount');

        return view('reports.daybook', compact(
            'dailyTotals', 'startDate', 'endDate', 'selectedCurrency', 'totalIn', 'totalOut', 'openingBalance'
        ));
    }

    public function aging(Request $request)
    {
        $currency = $request->get('currency', 'AFN');

        // Customer aging — based on outstanding orders, grouped by customer.
        // Orders with no linked customer are grouped as a "Walk-in" customer so they are not dropped.
        // NOTE: remaining_amount is an accessor, not a column. Compute it via SQL:
        // outstanding = total_amount - SUM(payments.amount) - SUM(order_returns.total_amount)
        // Note: order_returns has no currency column — we trust the parent order's currency.
        $customerOrders = Order::with(['customer', 'payments'])
            ->where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereRaw(
                'orders.total_amount > (
                    COALESCE((SELECT SUM(amount) FROM payments WHERE payments.order_id = orders.id), 0)
                    +
                    COALESCE((SELECT SUM(total_amount) FROM order_returns
                              WHERE order_returns.order_id = orders.id
                                AND order_returns.status != ?), 0)
                )',
                ['cancelled']
            )
            ->get();

        $customerAging = collect();
        foreach ($customerOrders->groupBy(fn($o) => $o->customer_id ?? 'walkin') as $key => $orderGroup) {
            $customer = $key === 'walkin'
                ? (object) ['name' => __('messages.walk_in_customer')]
                : ($orderGroup->first()->customer ?? (object) ['name' => __('messages.walk_in_customer')]);

            $mapped = $orderGroup->map(function ($order) {
                $days = (int) Carbon::parse($order->created_at->toDateString())->diffInDays(now());
                $bucket = $this->getAgeBucket($days);

                return [
                    'order_id' => $order->id,
                    'order_date' => $order->created_at->toDateString(),
                    'outstanding' => $order->remaining_amount,
                    'days' => $days,
                    'bucket' => $bucket,
                ];
            })->values();

            $bucketTotals = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
            foreach ($mapped as $order) {
                $bucketTotals[$order['bucket']] += $order['outstanding'];
            }

            $customerAging->push([
                'customer' => $customer,
                'orders' => $mapped,
                'bucket_totals' => $bucketTotals,
                'total_outstanding' => array_sum($bucketTotals),
            ]);
        }
        $customerAging = $customerAging->filter(fn($item) => $item['total_outstanding'] > 0)->values();

        $customerBucketTotal = [
            '0-30' => $customerAging->sum(fn($c) => $c['bucket_totals']['0-30']),
            '31-60' => $customerAging->sum(fn($c) => $c['bucket_totals']['31-60']),
            '61-90' => $customerAging->sum(fn($c) => $c['bucket_totals']['61-90']),
            '90+' => $customerAging->sum(fn($c) => $c['bucket_totals']['90+']),
        ];

        // Supplier aging — based on outstanding purchases, grouped by supplier.
        // Purchases with no linked supplier are grouped as a "Walk-in" supplier so they are not dropped.
        // Same accessor-as-column fix as above, mirrored for purchases.
        // Note: purchase_returns has no currency column — we trust the parent purchase's currency.
        $supplierPurchases = Purchase::with(['supplier', 'purchasePayments'])
            ->where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereRaw(
                'purchases.total_amount > (
                    COALESCE((SELECT SUM(amount) FROM purchase_payments WHERE purchase_payments.purchase_id = purchases.id), 0)
                    +
                    COALESCE((SELECT SUM(total_amount) FROM purchase_returns
                              WHERE purchase_returns.purchase_id = purchases.id
                                AND purchase_returns.status != ?), 0)
                )',
                ['cancelled']
            )
            ->get();

        $supplierAging = collect();
        foreach ($supplierPurchases->groupBy(fn($p) => $p->supplier_id ?? 'walkin') as $key => $purchaseGroup) {
            $supplier = $key === 'walkin'
                ? (object) ['name' => __('messages.walk_in_supplier')]
                : ($purchaseGroup->first()->supplier ?? (object) ['name' => __('messages.walk_in_supplier')]);

            $mapped = $purchaseGroup->map(function ($purchase) {
                $days = (int) Carbon::parse($purchase->created_at->toDateString())->diffInDays(now());
                $bucket = $this->getAgeBucket($days);

                return [
                    'purchase_id' => $purchase->id,
                    'purchase_date' => $purchase->created_at->toDateString(),
                    'outstanding' => $purchase->remaining_amount,
                    'days' => $days,
                    'bucket' => $bucket,
                ];
            })->values();

            $bucketTotals = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
            foreach ($mapped as $purchase) {
                $bucketTotals[$purchase['bucket']] += $purchase['outstanding'];
            }

            $supplierAging->push([
                'supplier' => $supplier,
                'purchases' => $mapped,
                'bucket_totals' => $bucketTotals,
                'total_outstanding' => array_sum($bucketTotals),
            ]);
        }
        $supplierAging = $supplierAging->filter(fn($item) => $item['total_outstanding'] > 0)->values();

        $supplierBucketTotal = [
            '0-30' => $supplierAging->sum(fn($s) => $s['bucket_totals']['0-30']),
            '31-60' => $supplierAging->sum(fn($s) => $s['bucket_totals']['31-60']),
            '61-90' => $supplierAging->sum(fn($s) => $s['bucket_totals']['61-90']),
            '90+' => $supplierAging->sum(fn($s) => $s['bucket_totals']['90+']),
        ];

        $selectedCurrency = $currency;

        return view('reports.aging', compact(
            'customerAging', 'customerBucketTotal',
            'supplierAging', 'supplierBucketTotal',
            'selectedCurrency'
        ));
    }

    private function getAgeBucket(int $days): string
    {
        if ($days <= 30) {
            return '0-30';
        } elseif ($days <= 60) {
            return '31-60';
        } elseif ($days <= 90) {
            return '61-90';
        } else {
            return '90+';
        }
    }
}