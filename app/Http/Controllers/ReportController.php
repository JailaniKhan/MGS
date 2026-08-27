<?php

namespace App\Http\Controllers;

use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\SalaryPayment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\Accounting\BalanceService;
use App\Services\Reporting\UnitProfitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        private BalanceService $balanceService,
        private UnitProfitService $unitProfit,
    ) {}

    public function profitLoss(Request $request)
    {
        $anchor = Carbon::parse($request->get('date_to', now()->toDateString()));
        $period = $request->get('period', 'month');
        $currency = $request->get('currency', 'AFN');

        $anchorDate = $anchor->toDateString();
        $startDateTime = match ($period) {
            '30' => $anchor->copy()->subDays(29)->startOfDay(),
            'quarter' => $anchor->copy()->startOfQuarter(),
            'year' => $anchor->copy()->startOfYear(),
            default => $anchor->copy()->startOfMonth(),
        };
        $endDateTime = $anchor->copy()->endOfDay();

        // Total Revenue from non-cancelled orders (net of returns)
        $totalRevenue = Order::where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('total_amount');
        // return_date is stored date-only ('Y-m-d'), so an inclusive lower bound on
        // the date string plus an exclusive upper bound keeps first-day rows in range
        // (a `<=` endOfDay / `>=` startOfDay string compare would drop them).
        $toExclusive = $endDateTime->copy()->addDay()->toDateString();
        $orderReturns = OrderReturn::where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->where('return_date', '>=', $startDateTime->toDateString())
            ->where('return_date', '<', $toExclusive)
            ->sum('total_amount');
        $totalRevenue = bcsub((string) $totalRevenue, (string) $orderReturns, 2);

        // True per-unit margin: cost of goods SOLD = weighted average purchase
        // price per product per currency × sold quantity (not purchases made in
        // the period — stock bought but unsold stays out of this P&L).
        // Purchase returns do NOT adjust the cost basis; sales without any
        // matching purchase in this currency count revenue at zero cost and are
        // surfaced to the view as missing_cost_lines.
        $unitProfitData = $this->unitProfit->forPeriod($startDateTime, $endDateTime, 50, $currency);
        $unitTotals = $unitProfitData['totals'][$currency];
        $totalCOGS = $unitTotals['cost'];

        // Operating expenses
        $cashExpenses = CashbookEntry::where('type', 'out')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('amount');
        $salaryExpenses = SalaryPayment::where('currency', $currency)
            ->whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('amount');
        // Include the dedicated Expense table (Fix C4)
        $operatingExpenses = Expense::where('currency', $currency)
            ->where('expense_date', '>=', $startDateTime->toDateString())
            ->where('expense_date', '<', $toExclusive)
            ->sum('amount');

        $totalExpenses = bcadd(bcadd((string) $totalCOGS, (string) $cashExpenses, 2), bcadd((string) $salaryExpenses, (string) $operatingExpenses, 2), 2);
        $netProfit = bcsub((string) $totalRevenue, (string) $totalExpenses, 2);

        $grossProfit = bcsub((string) $totalRevenue, (string) $totalCOGS, 2);
        $netMarginPercent = bccomp((string) $totalRevenue, '0', 2) === 1
            ? bcdiv(bcmul((string) $netProfit, '100', 2), (string) $totalRevenue, 1)
            : null;

        $selectedCurrency = $currency;

        return view('reports.profit_loss', compact(
            'totalRevenue', 'totalCOGS', 'cashExpenses', 'salaryExpenses', 'operatingExpenses',
            'totalExpenses', 'netProfit', 'grossProfit', 'netMarginPercent',
            'unitProfitData', 'unitTotals',
            'anchorDate', 'period', 'selectedCurrency'
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
            bcadd(
                (string) SalaryPayment::where('currency', $currency)->whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))->sum('amount'),
                (string) Expense::where('currency', $currency)->sum('amount'),
                2
            ),
            2
        );
        $cashBalance = bcsub($cashIn, $cashOut, 2);

        // Accounts Receivable (what customers owe us) = sum of order remaining_amount per currency.
        // Single grouped SQL: each order's remaining is computed via subselects, clamped to 0 per row,
        // then the outer query sums. One round trip instead of two SQL per order.
        // groupBy(orders.id) is REQUIRED: without it SQLite's bare-column aggregation picks an
        // arbitrary single row, silently returning one order's remainder instead of the sum.
        $receivableRow = DB::query()->fromSub(function ($q) use ($currency) {
            $q->from('orders')
                ->selectRaw("MAX(orders.total_amount - COALESCE((SELECT SUM(amount) FROM payments WHERE payments.order_id = orders.id AND payments.currency = orders.currency), 0) - COALESCE((SELECT SUM(total_amount) FROM order_returns WHERE order_returns.order_id = orders.id AND order_returns.status != 'cancelled' AND order_returns.currency = orders.currency), 0), 0) AS remaining")
                ->where('orders.user_id', Auth::id())
                ->where('orders.currency', $currency)
                ->where('orders.status', '!=', 'cancelled')
                ->groupBy('orders.id');
        }, 'per_order')->sum('remaining');
        $receivables = bcadd('0.00', (string) ($receivableRow ?: '0'), 2);

        // Inventory Value (stock * weighted average purchase price). One grouped query per product_id
        // over purchase_items joined to parent currency+status, instead of one AVG query per product.
        // Weighted (unit_price × qty) / qty matches the stock report, so both pages agree.
        $avgPurchaseByProduct = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.user_id', Auth::id())
            ->where('purchases.currency', $currency)
            ->where('purchases.status', '!=', 'cancelled')
            ->select('purchase_items.product_id', DB::raw('SUM(purchase_items.unit_price * purchase_items.quantity) * 1.0 / NULLIF(SUM(purchase_items.quantity), 0) as avg_price'))
            ->groupBy('purchase_items.product_id')
            ->pluck('avg_price', 'product_id')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''));

        $inventoryValue = '0.00';
        foreach (Product::select('id', 'stock_afn', 'stock_usd')->get() as $product) {
            $avgPrice = $avgPurchaseByProduct[$product->id] ?? '0.00';
            // Value the pool that matches the currency: USD stock × USD avg cost.
            $lineValue = bcmul((string) $product->stockFor($currency), $avgPrice, 2);
            $inventoryValue = bcadd($inventoryValue, $lineValue, 2);
        }

        $totalAssets = bcadd($cashBalance, $receivables, 2);
        $totalAssets = bcadd($totalAssets, (string) $inventoryValue, 2);

        // LIABILITIES
        // Accounts Payable (what we owe suppliers) = sum of purchase remaining_amount per currency.
        // groupBy(purchases.id): see the receivables query above for why bare MAX is unsafe.
        $payableRow = DB::query()->fromSub(function ($q) use ($currency) {
            $q->from('purchases')
                ->selectRaw("MAX(purchases.total_amount - COALESCE((SELECT SUM(amount) FROM purchase_payments WHERE purchase_payments.purchase_id = purchases.id AND purchase_payments.currency = purchases.currency), 0) - COALESCE((SELECT SUM(total_amount) FROM purchase_returns WHERE purchase_returns.purchase_id = purchases.id AND purchase_returns.status != 'cancelled' AND purchase_returns.currency = purchases.currency), 0), 0) AS remaining")
                ->where('purchases.user_id', Auth::id())
                ->where('purchases.currency', $currency)
                ->where('purchases.status', '!=', 'cancelled')
                ->groupBy('purchases.id');
        }, 'per_purchase')->sum('remaining');
        $payables = bcadd('0.00', (string) ($payableRow ?: '0'), 2);

        // EQUITY
        // Retained Earnings = lifetime revenue (net of returns) - lifetime expenses.
        $totalRevenue = Order::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $orderReturns = OrderReturn::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $totalRevenue = bcsub((string) $totalRevenue, (string) $orderReturns, 2);

        // Cashbook-in entries are cash income with no order attached (matching cashbook-out
        // being treated as an expense). They count in Cash in Hand above, so they must also sit
        // in equity here — otherwise Assets = Liabilities + Equity can never balance.
        $cashIncome = CashbookEntry::where('type', 'in')->where('currency', $currency)->sum('amount');
        $totalRevenue = bcadd($totalRevenue, (string) $cashIncome, 2);

        $purchasesCost = Purchase::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $purchaseReturns = PurchaseReturn::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $cogs = bcsub((string) $purchasesCost, (string) $purchaseReturns, 2);
        // COGS nets out ending inventory still on hand; otherwise purchases would be counted
        // both as an expense here and as an asset above (double-counting).
        $cogs = bcsub($cogs, (string) $inventoryValue, 2);

        $operating = bcadd(
            bcadd(
                (string) CashbookEntry::where('type', 'out')->where('currency', $currency)->sum('amount'),
                (string) SalaryPayment::where('currency', $currency)->whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))->sum('amount'),
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
            'payables', 'retainedEarnings', 'ownerCapital', 'totalEquity',
            'totalLiabilitiesEquity', 'selectedCurrency'
        ));
    }

    public function stockReport(Request $request)
    {
        $currency = $request->get('currency', 'AFN');
        $minStockThreshold = Setting::get('min_stock_threshold', 10);

        $products = Product::select('id', 'name', 'lot_number', 'stock_afn', 'stock_usd', 'category_id')->with('category:id,name')->get();

        // One grouped query per relation (not one per product), mapped back per product_id.
        // Weighted average = SUM(unit_price × quantity) / SUM(quantity). A plain AVG(unit_price)
        // treats a 1-unit purchase at 100 and a 100-unit purchase at 10 identically (avg 55),
        // which misstates the average cost a unit of stock is actually carried at (~10.9).
        $avgPurchaseByProduct = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.user_id', Auth::id())
            ->where('purchases.currency', $currency)
            ->where('purchases.status', '!=', 'cancelled')
            ->select('purchase_items.product_id',
                DB::raw('SUM(purchase_items.unit_price * purchase_items.quantity) * 1.0 / NULLIF(SUM(purchase_items.quantity), 0) as avg_price'))
            ->groupBy('purchase_items.product_id')
            ->pluck('avg_price', 'product_id')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''));

        $avgSaleByProduct = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', Auth::id())
            ->where('orders.currency', $currency)
            ->where('orders.status', '!=', 'cancelled')
            ->select('order_items.product_id',
                DB::raw('SUM(order_items.unit_price * order_items.quantity) * 1.0 / NULLIF(SUM(order_items.quantity), 0) as avg_price'))
            ->groupBy('order_items.product_id')
            ->pluck('avg_price', 'product_id')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''));

        $stockData = $products->map(function ($product) use ($currency, $minStockThreshold, $avgPurchaseByProduct, $avgSaleByProduct) {
            $avgPurchasePrice = $avgPurchaseByProduct[$product->id] ?? '0.00';
            $avgSalePrice = $avgSaleByProduct[$product->id] ?? '0.00';

            // bcmul returns a string; keep it string to preserve precision up to the view layer.
            // Value the pool matching the report's currency.
            $poolQty = $product->stockFor($currency);
            $stockValue = bcmul((string) $poolQty, $avgPurchasePrice, 2);

            return [
                'product' => $product,
                'pool_qty' => $poolQty,
                'avg_purchase_price' => $avgPurchasePrice,
                'avg_sale_price' => $avgSalePrice,
                'stock_value' => $stockValue,
                // Same rule as Dashboard/Inventory: a pool only runs "low" when it
                // exists but ran thin — a zero pool just means this currency was
                // never traded, which must not flood the banner on currency switch.
                'is_low_stock' => $minStockThreshold > 0 && $poolQty > 0 && $poolQty < $minStockThreshold,
            ];
        });

        $lowStockProducts = $stockData->filter(fn ($item) => $item['is_low_stock']);

        $totalStockValue = '0.00';
        foreach ($stockData as $item) {
            $totalStockValue = bcadd($totalStockValue, $item['stock_value'], 2);
        }

        $selectedCurrency = $currency;

        return view('reports.stock_report', compact(
            'stockData', 'lowStockProducts', 'totalStockValue', 'selectedCurrency', 'minStockThreshold'
        ));
    }

    public function daybook(Request $request)
    {
        $anchor = Carbon::parse($request->get('date_to', now()->toDateString()));
        $period = $request->get('period', '30');
        $currency = $request->get('currency', 'AFN');
        $selectedCurrency = $currency;

        $anchorDate = $anchor->toDateString();
        $endDate = $anchor->copy()->endOfDay();
        $startDate = match ($period) {
            '7' => $anchor->copy()->subDays(6)->startOfDay(),
            '90' => $anchor->copy()->subDays(89)->startOfDay(),
            'month' => $anchor->copy()->startOfMonth(),
            default => $anchor->copy()->subDays(29)->startOfDay(),
        };

        $typeLabels = [
            'sale' => __('messages.type_sale'),
            'purchase_payment' => __('messages.type_purchase_payment'),
            'customer_payment' => __('messages.type_customer_payment'),
            'cash_in' => __('messages.type_cash_in'),
            'cash_out' => __('messages.type_cash_out'),
        ];

        $transactions = collect();

        // Sales (completed orders) — recorded as a non-cash accrual line so they appear in the
        // daybook for reference, but do NOT inflate the cash running balance (Fix M4).
        $sales = Order::with('customer')
            ->where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($order) use ($typeLabels) {
                return [
                    'date' => $order->created_at->toDateString(),
                    'type' => 'sale',
                    'type_label' => $typeLabels['sale'],
                    'description' => __('messages.sales_dash').optional($order->customer)->name,
                    'in_amount' => 0,
                    'out_amount' => 0,
                    'amount' => (string) $order->total_amount,
                    'created_at' => $order->created_at,
                ];
            });

        // Purchase payments (money going out for purchases)
        $purchasePayments = PurchasePayment::with('purchase.supplier')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($payment) use ($typeLabels) {
                return [
                    'date' => $payment->created_at->toDateString(),
                    'type' => 'purchase_payment',
                    'type_label' => $typeLabels['purchase_payment'],
                    'description' => __('messages.pending_purchase_dash').optional($payment->purchase)->party?->name,
                    'in_amount' => 0,
                    'out_amount' => (string) $payment->amount,
                    'created_at' => $payment->created_at,
                ];
            });

        // Customer payments (money coming in)
        $customerPayments = Payment::with('order.customer')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($payment) use ($typeLabels) {
                return [
                    'date' => $payment->created_at->toDateString(),
                    'type' => 'customer_payment',
                    'type_label' => $typeLabels['customer_payment'],
                    'description' => __('messages.supplier_delivery_dash').optional($payment->order->customer)->name,
                    'in_amount' => (string) $payment->amount,
                    'out_amount' => 0,
                    'created_at' => $payment->created_at,
                ];
            });

        // Cash entries
        $cashEntries = CashbookEntry::where('currency', $currency)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->get()
            ->map(function ($entry) use ($typeLabels) {
                return [
                    'date' => $entry->entry_date->toDateString(),
                    'type' => 'cash_'.$entry->type,
                    'type_label' => $entry->type === 'in' ? $typeLabels['cash_in'] : $typeLabels['cash_out'],
                    'description' => __('messages.cashbook_section').': '.($entry->type === 'in' ? __('messages.income') : __('messages.expense_out')).($entry->notes ? ' - '.$entry->notes : ''),
                    'in_amount' => $entry->type === 'in' ? (string) $entry->amount : 0,
                    'out_amount' => $entry->type === 'out' ? (string) $entry->amount : 0,
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

        $totalIn = '0.00';
        $totalOut = '0.00';
        foreach ($transactions as $tx) {
            $totalIn = bcadd($totalIn, (string) $tx['in_amount'], 2);
            $totalOut = bcadd($totalOut, (string) $tx['out_amount'], 2);
        }

        // Opening balance is DERIVED by backing the period's net movement out of
        // the live cash position (BalanceService::cashBalance is current-only, no
        // as-of-date variant). This keeps the running balance self-consistent:
        // the oldest day starts at the true opening and the newest day lands
        // exactly on the live cash balance. Adding the period net to the current
        // balance instead would double-count the period's movements.
        $periodNet = bcsub($totalIn, $totalOut, 2);
        $openingBalance = bcsub(
            $this->balanceService->cashBalance(Auth::id(), $currency),
            $periodNet,
            2
        );

        // Group by date
        $dailyTotals = [];

        foreach ($transactions as $tx) {
            $date = $tx['date'];
            if (! isset($dailyTotals[$date])) {
                $dailyTotals[$date] = [
                    'date' => $date,
                    'in_total' => '0.00',
                    'out_total' => '0.00',
                    'transactions' => [],
                ];
            }
            $dailyTotals[$date]['transactions'][] = $tx;
            $dailyTotals[$date]['in_total'] = bcadd($dailyTotals[$date]['in_total'], (string) $tx['in_amount'], 2);
            $dailyTotals[$date]['out_total'] = bcadd($dailyTotals[$date]['out_total'], (string) $tx['out_amount'], 2);
        }

        // Accumulate oldest → newest so each day carries its true running balance,
        // then present newest-first. (Accumulating newest → oldest would put the
        // closing balance on the OLDEST row and only opening+own-net on today's row.)
        $dailyTotals = collect($dailyTotals)->sortBy('date')->values();

        $runningBalance = $openingBalance;
        $dailyTotals = $dailyTotals->map(function ($day) use (&$runningBalance) {
            $runningBalance = bcadd($runningBalance, bcsub($day['in_total'], $day['out_total'], 2), 2);
            $day['running_balance'] = $runningBalance;

            return $day;
        })->sortByDesc('date')->values();

        $closingBalance = $runningBalance;

        return view('reports.daybook', compact(
            'dailyTotals', 'anchorDate', 'period', 'selectedCurrency', 'totalIn', 'totalOut',
            'openingBalance', 'closingBalance'
        ));
    }

    public function aging(Request $request)
    {
        $currency = $request->get('currency', 'AFN');

        // Customer aging — outstanding orders grouped by their real party (person_type/person_id),
        // with customer_id as a legacy fallback. Supplier-type orders keep their supplier's name
        // instead of being lumped into a fake "Walk-in Customer" bucket.
        // outstanding = total_amount - SUM(payments.amount) - SUM(order_returns.total_amount)
        // Every money side is restricted to the order's own currency: a payment or return recorded
        // in a different currency must not offset (or silently drop) the balance in this currency.
        $customerOrders = Order::with(['customer', 'supplier'])
            ->withSum(['payments as paid_sum' => fn ($q) => $q->whereColumn('payments.currency', 'orders.currency')], 'amount')
            ->withSum(['orderReturns as returned_sum' => fn ($q) => $q
                ->whereColumn('order_returns.currency', 'orders.currency')
                ->where('status', '!=', 'cancelled')], 'total_amount')
            ->where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereRaw(
                'orders.total_amount > (
                    COALESCE((SELECT SUM(amount) FROM payments
                              WHERE payments.order_id = orders.id
                                AND payments.currency = orders.currency), 0)
                    +
                    COALESCE((SELECT SUM(total_amount) FROM order_returns
                              WHERE order_returns.order_id = orders.id
                                AND order_returns.status != ?
                                AND order_returns.currency = orders.currency), 0)
                )',
                ['cancelled']
            )
            ->get();

        $customerAging = collect();
        // Group key includes person_type: a customer and a supplier can share the same
        // person_id, and lumping them together would merge unrelated debts under one name.
        foreach ($customerOrders->groupBy(fn ($o) => ($o->person_type ?? 'customer').':'.($o->person_id ?? $o->customer_id ?? 'walkin')) as $key => $orderGroup) {
            $party = $orderGroup->first()->party;
            $customer = $party ?? (object) ['name' => __('messages.walk_in_customer')];

            $mapped = $orderGroup->map(function ($order) {
                $days = (int) Carbon::parse($order->created_at->toDateString())->diffInDays(now());
                $bucket = $this->getAgeBucket($days);

                return [
                    'order_id' => $order->id,
                    'order_date' => $order->created_at->toDateString(),
                    'outstanding' => $this->outstandingAmount($order->total_amount, $order->paid_sum, $order->returned_sum),
                    'days' => $days,
                    'bucket' => $bucket,
                ];
            })->values();

            $bucketTotals = $this->emptyBucketTotals();
            foreach ($mapped as $order) {
                $bucketTotals[$order['bucket']] = bcadd($bucketTotals[$order['bucket']], $order['outstanding'], 2);
            }

            $customerAging->push([
                'customer' => $customer,
                'orders' => $mapped,
                'bucket_totals' => $bucketTotals,
                'total_outstanding' => $this->sumBuckets($bucketTotals),
            ]);
        }
        $customerAging = $customerAging
            ->filter(fn ($item) => bccomp($item['total_outstanding'], '0', 2) > 0)
            // Cast to float: total_outstanding is a decimal string, and a lexicographic
            // sort would rank "900.00" above "1000.00".
            ->sortByDesc(fn ($item) => (float) $item['total_outstanding'])
            ->values();

        $customerBucketTotal = $this->aggregateBuckets($customerAging);

        // Supplier aging — outstanding purchases grouped by their real party, supplier_id as a
        // legacy fallback. Mirrors the customer side: party-aware grouping and currency-matched
        // payments/returns so foreign-currency money never distorts a currency's balance.
        $supplierPurchases = Purchase::with(['customer', 'supplier'])
            ->withSum(['purchasePayments as paid_sum' => fn ($q) => $q->whereColumn('purchase_payments.currency', 'purchases.currency')], 'amount')
            ->withSum(['purchaseReturns as returned_sum' => fn ($q) => $q
                ->whereColumn('purchase_returns.currency', 'purchases.currency')
                ->where('status', '!=', 'cancelled')], 'total_amount')
            ->where('status', '!=', 'cancelled')
            ->where('currency', $currency)
            ->whereRaw(
                'purchases.total_amount > (
                    COALESCE((SELECT SUM(amount) FROM purchase_payments
                              WHERE purchase_payments.purchase_id = purchases.id
                                AND purchase_payments.currency = purchases.currency), 0)
                    +
                    COALESCE((SELECT SUM(total_amount) FROM purchase_returns
                              WHERE purchase_returns.purchase_id = purchases.id
                                AND purchase_returns.status != ?
                                AND purchase_returns.currency = purchases.currency), 0)
                )',
                ['cancelled']
            )
            ->get();

        $supplierAging = collect();
        // Same person_type-aware grouping as the customer side (see note above).
        foreach ($supplierPurchases->groupBy(fn ($p) => ($p->person_type ?? 'supplier').':'.($p->person_id ?? $p->supplier_id ?? 'walkin')) as $key => $purchaseGroup) {
            $party = $purchaseGroup->first()->party
                ?? ($purchaseGroup->first()->supplier_id ? Supplier::find($purchaseGroup->first()->supplier_id) : null);
            $supplier = $party ?? (object) ['name' => __('messages.walk_in_supplier')];

            $mapped = $purchaseGroup->map(function ($purchase) {
                $days = (int) Carbon::parse($purchase->created_at->toDateString())->diffInDays(now());
                $bucket = $this->getAgeBucket($days);

                return [
                    'purchase_id' => $purchase->id,
                    'purchase_date' => $purchase->created_at->toDateString(),
                    'outstanding' => $this->outstandingAmount($purchase->total_amount, $purchase->paid_sum, $purchase->returned_sum),
                    'days' => $days,
                    'bucket' => $bucket,
                ];
            })->values();

            $bucketTotals = $this->emptyBucketTotals();
            foreach ($mapped as $purchase) {
                $bucketTotals[$purchase['bucket']] = bcadd($bucketTotals[$purchase['bucket']], $purchase['outstanding'], 2);
            }

            $supplierAging->push([
                'supplier' => $supplier,
                'purchases' => $mapped,
                'bucket_totals' => $bucketTotals,
                'total_outstanding' => $this->sumBuckets($bucketTotals),
            ]);
        }
        $supplierAging = $supplierAging
            ->filter(fn ($item) => bccomp($item['total_outstanding'], '0', 2) > 0)
            ->sortByDesc(fn ($item) => (float) $item['total_outstanding'])
            ->values();

        $supplierBucketTotal = $this->aggregateBuckets($supplierAging);

        $customerGrandTotal = $this->sumBuckets($customerBucketTotal);
        $supplierGrandTotal = $this->sumBuckets($supplierBucketTotal);
        $selectedCurrency = $currency;
        $currencySymbol = $currency === 'USD' ? '$' : __('messages.afn');

        return view('reports.aging', compact(
            'customerAging', 'customerBucketTotal',
            'supplierAging', 'supplierBucketTotal',
            'customerGrandTotal', 'supplierGrandTotal',
            'selectedCurrency', 'currencySymbol'
        ));
    }

    /**
     * Outstanding = total - paid - returned, clamped at zero, all in the same currency.
     * paid/returned come from withSum aggregate columns (null when no rows exist).
     */
    private function outstandingAmount(string $total, $paid, $returned): string
    {
        $remaining = bcsub((string) $total, (string) ($paid ?? '0'), 2);
        $remaining = bcsub($remaining, (string) ($returned ?? '0'), 2);

        return bccomp($remaining, '0', 2) >= 0 ? $remaining : '0.00';
    }

    /**
     * @return array{0-30: string, 31-60: string, 61-90: string, 90+: string}
     */
    private function emptyBucketTotals(): array
    {
        return ['0-30' => '0.00', '31-60' => '0.00', '61-90' => '0.00', '90+' => '0.00'];
    }

    private function sumBuckets(array $bucketTotals): string
    {
        return bcadd(
            bcadd(bcadd($bucketTotals['0-30'], $bucketTotals['31-60'], 2), $bucketTotals['61-90'], 2),
            $bucketTotals['90+'],
            2
        );
    }

    private function aggregateBuckets($aging): array
    {
        $totals = $this->emptyBucketTotals();
        foreach ($aging as $item) {
            foreach ($totals as $bucket => $_) {
                $totals[$bucket] = bcadd($totals[$bucket], $item['bucket_totals'][$bucket], 2);
            }
        }

        return $totals;
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
