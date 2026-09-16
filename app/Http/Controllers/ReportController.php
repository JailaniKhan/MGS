<?php

namespace App\Http\Controllers;

use App\Models\CashbookEntry;
use App\Models\Expense;
use App\Models\JournalEntry;
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
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\CashFlowService;
use App\Services\Reporting\LandedCostService;
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
        private LandedCostService $landedCost,
    ) {}

    public function profitLoss(Request $request)
    {
        $anchor = Carbon::parse($request->get('date_to', now()->toDateString()));
        $currency = $request->get('currency', 'AFN');

        $anchorDate = $anchor->toDateString();
        // All Time window: from the user's earliest record to the anchor's
        // end of day. The end date input still bounds the window's end.
        $startDateTime = $this->earliestRecordStart($anchor);
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
        // Cash expenses cover BOTH cashbook generations (journal entries posted
        // by the live cashbook form + legacy cashbook_entries rows); the window
        // uses the same inclusive-lower / exclusive-upper date bound as the
        // return/expense queries above because date-only columns may hold either
        // 'Y-m-d' or a full timestamp.
        $cashExpenses = app(CashFlowService::class)->cashbookOutBetween(
            $currency,
            $startDateTime->toDateString(),
            $toExclusive
        );
        $salaryExpenses = SalaryPayment::where('currency', $currency)
            ->whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('amount');
        // Operating expenses. Linked (landed-cost) expenses are EXCLUDED here —
        // they already sit in COGS via the landed-inclusive cost basis, and
        // counting them twice would understate profit by the freight amount.
        $operatingExpenses = Expense::where('currency', $currency)
            ->whereNull('purchase_id')
            ->where('expense_date', '>=', $startDateTime->toDateString())
            ->where('expense_date', '<', $toExclusive)
            ->sum('amount');

        // Period's landed costs (linked expenses) for the COGS badge: the
        // landed basis spans all history, so the badge reports what was
        // linked within this window in this currency.
        $landedCosts = Expense::where('currency', $currency)
            ->whereNotNull('purchase_id')
            ->whereIn('purchase_id', Purchase::where('user_id', Auth::id())->where('status', '!=', 'cancelled')->pluck('id'))
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
            'unitProfitData', 'unitTotals', 'landedCosts',
            'anchorDate', 'selectedCurrency'
        ));
    }

    /**
     * Start of the "All Time" P&L window: the earliest record this report
     * aggregates — orders, order returns, cashbook entries (both the legacy
     * table and the journal generation the live cashbook form posts),
     * expenses and salary payments (all user-scoped the same way the
     * aggregates above are). No currency/status filters: a foreign-currency
     * or cancelled row starting the window earlier shifts no numbers, the
     * window is only a container. A fresh install (no records at all)
     * reports from today.
     */
    private function earliestRecordStart(Carbon $anchor): Carbon
    {
        $dates = [
            Order::min('created_at'),
            OrderReturn::min('return_date'),
            // The P&L filters legacy cashbook rows by entry_date, so the
            // window must anchor on it — a backdated entry (entry_date before
            // every created_at) still belongs to the window it expenses.
            CashbookEntry::min('entry_date'),
            JournalEntry::min('transaction_date'),
            Expense::min('expense_date'),
            SalaryPayment::whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))->min('created_at'),
        ];

        $earliest = collect($dates)->filter()->sort()->first();

        return $earliest !== null
            ? Carbon::parse($earliest)->startOfDay()
            : $anchor->copy()->startOfDay();
    }

    public function balanceSheet(Request $request, CashFlowService $cashFlow)
    {
        $currency = $request->get('currency', 'AFN');

        // ASSETS
        // Cash in hand, from the canonical cash-flow union (payments, ledger
        // payments, BOTH cashbook generations, expenses, salaries) — the same
        // figure the wallet hero and the daybook closing balance show.
        $totals = $cashFlow->totals();
        $cashBalance = number_format($totals[$currency]['balance'], 2, '.', '');

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

        // Inventory Value (stock × landed-inclusive weighted average purchase
        // price). One shared service call keeps the stock report, P&L and this
        // sheet on the same cost basis. Flatten to product_id => price for the
        // single-currency lookups below.
        $avgPurchaseByProduct = collect($this->landedCost->weightedAverageCost(Auth::id(), $currency))
            ->map(fn ($prices) => $prices[$currency] ?? '0.00');

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
        // Both cashbook generations count (journal entries + legacy rows).
        $cashIncome = $cashFlow->cashbookIn($currency);
        $totalRevenue = bcadd($totalRevenue, $cashIncome, 2);

        $purchasesCost = Purchase::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $purchaseReturns = PurchaseReturn::where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');
        $cogs = bcsub((string) $purchasesCost, (string) $purchaseReturns, 2);
        // COGS nets out ending inventory still on hand; otherwise purchases would be counted
        // both as an expense here and as an asset above (double-counting).
        $cogs = bcsub($cogs, (string) $inventoryValue, 2);

        $operating = bcadd(
            bcadd(
                $cashFlow->cashbookOut($currency),
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
        // Weighted average = SUM(unit_price × quantity) / SUM(quantity), landed-cost
        // inclusive (linked expenses allocated by line value) so the stock report,
        // the P&L and the balance sheet all value inventory on the same basis.
        // Flattened to product_id => price for the single-currency lookups below.
        $avgPurchaseByProduct = collect($this->landedCost->weightedAverageCost(Auth::id(), $currency))
            ->map(fn ($prices) => $prices[$currency] ?? '0.00');

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

    /**
     * Single-day daybook: every cash movement of one date (default today),
     * with the opening balance derived from ALL cash history before that
     * date and the closing balance matching the balance sheet's Cash in Hand.
     */
    public function daybook(Request $request)
    {
        $validated = validator($request->all(), [
            'date_to' => ['nullable', 'date'],
            'currency' => ['nullable', 'in:AFN,USD'],
        ])->validate();

        $anchorDate = $validated['date_to'] ?? now()->toDateString();
        $currency = $validated['currency'] ?? 'AFN';
        $selectedCurrency = $currency;

        $anchor = Carbon::parse($anchorDate);
        $startDate = $anchor->copy()->startOfDay();
        $endDate = $anchor->copy()->endOfDay();

        $typeLabels = [
            'sale' => __('messages.type_sale'),
            'purchase_payment' => __('messages.type_purchase_payment'),
            'customer_payment' => __('messages.type_customer_payment'),
            'cash_in' => __('messages.type_cash_in'),
            'cash_out' => __('messages.type_cash_out'),
            'expense_out' => __('messages.expense_out'),
            'salary' => __('messages.salary_dash'),
            'party_payment_in' => __('messages.payment_from'),
            'party_payment_out' => __('messages.payment_to'),
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
                    'description' => __('messages.payment_to').optional($payment->purchase?->party)->name,
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
                    'description' => __('messages.payment_from').optional($payment->order->customer)->name,
                    'in_amount' => (string) $payment->amount,
                    'out_amount' => 0,
                    'created_at' => $payment->created_at,
                ];
            });

        // Cashbook entries — BOTH representations. The live CashbookController posts
        // journal entries (source cashbook_in/out) since Fix C2; legacy rows in
        // cashbook_entries predate it. The two sources are disjoint (the double-entry
        // migration command migrates neither), so summing both never double-counts.
        // Date-cast columns store "Y-m-d 00:00:00" in SQLite, so the day window uses
        // an inclusive-lower / exclusive-upper bound — bare equality on "Y-m-d"
        // never matches a time-suffixed value.
        $journalCashbook = JournalEntry::with(['reference', 'ledgerEntries'])
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->where('currency', $currency)
            ->where('transaction_date', '>=', $startDate->toDateString())
            ->where('transaction_date', '<', $endDate->copy()->addDay()->toDateString())
            ->get()
            ->map(function ($journal) use ($typeLabels) {
                $cashLine = $journal->ledgerEntries->first();
                $isIn = $journal->source === 'cashbook_in';
                $party = $journal->reference?->name;
                $notes = $journal->ledgerEntries->pluck('notes')->filter()->first();
                $description = trim(implode(' - ', array_filter([
                    $journal->description,
                    $party,
                    $notes,
                ])));

                return [
                    'date' => $journal->transaction_date->toDateString(),
                    'type' => $isIn ? 'cash_in' : 'cash_out',
                    'type_label' => $isIn ? $typeLabels['cash_in'] : $typeLabels['cash_out'],
                    'description' => $description !== '' ? $description : ($isIn ? __('messages.cash_income') : __('messages.cash_expense')),
                    'in_amount' => $isIn ? (string) ($cashLine->amount ?? '0') : 0,
                    'out_amount' => ! $isIn ? (string) ($cashLine->amount ?? '0') : 0,
                    'created_at' => $journal->created_at,
                ];
            });

        $legacyCashbook = CashbookEntry::where('currency', $currency)
            ->where('entry_date', '>=', $startDate->toDateString())
            ->where('entry_date', '<', $endDate->copy()->addDay()->toDateString())
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

        // Salary payments (money going out; posted to the journal with source
        // 'salary' and mirrored in salary_payments).
        $salaryRows = SalaryPayment::with('employee')
            ->where('currency', $currency)
            ->whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($salary) use ($typeLabels) {
                return [
                    'date' => $salary->created_at->toDateString(),
                    'type' => 'salary',
                    'type_label' => $typeLabels['salary'],
                    'description' => __('messages.salary_dash').optional($salary->employee)->name.($salary->notes ? ' - '.$salary->notes : ''),
                    'in_amount' => 0,
                    'out_amount' => (string) $salary->amount,
                    'created_at' => $salary->created_at,
                ];
            });

        // Unallocated party payments (PaymentAllocationService keep-on-account
        // remainders). These are real cash that never lands in Payment /
        // PurchasePayment, so the daybook must show them or the day's cash
        // would not reconcile with the balance sheet.
        $partyRows = PartyPayment::with('person')
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($partyPayment) use ($typeLabels) {
                $isIn = $partyPayment->type === 'payment_received';

                return [
                    'date' => $partyPayment->created_at->toDateString(),
                    'type' => $isIn ? 'party_payment_in' : 'party_payment_out',
                    'type_label' => $isIn ? $typeLabels['party_payment_in'] : $typeLabels['party_payment_out'],
                    'description' => ($isIn ? __('messages.payment_from') : __('messages.payment_to')).optional($partyPayment->person)->name.($partyPayment->notes ? ' - '.$partyPayment->notes : ''),
                    'in_amount' => $isIn ? (string) $partyPayment->amount : 0,
                    'out_amount' => ! $isIn ? (string) $partyPayment->amount : 0,
                    'created_at' => $partyPayment->created_at,
                ];
            });

        // Expense rows (money going out for regular + landed-cost expenses).
        // expense_date is date-only, so the same inclusive-lower / exclusive-
        // upper bound pattern as the P&L applies here. Linked (landed) ones
        // mention their purchase so the daybook shows where the money went.
        $expenseRows = Expense::with('purchase.purchaseItems')
            ->where('currency', $currency)
            ->where('expense_date', '>=', $startDate->toDateString())
            ->where('expense_date', '<', $endDate->copy()->addDay()->toDateString())
            ->get()
            ->map(function ($expense) use ($typeLabels) {
                $description = __('messages.expense_out').': '.$expense->category;
                if ($expense->purchase) {
                    $lots = $expense->purchase->purchaseItems
                        ->pluck('lot_number')
                        ->filter(fn ($lot) => $lot !== null && trim((string) $lot) !== '')
                        ->unique()
                        ->implode(', ');
                    $description .= ' · '.__('messages.purchase').' #'.$expense->purchase->id
                        .($lots !== '' ? ' ('.__('messages.lot').' '.$lots.')' : '');
                } elseif ($expense->notes) {
                    $description .= ' - '.$expense->notes;
                }

                return [
                    'date' => $expense->expense_date->toDateString(),
                    'type' => 'expense',
                    'type_label' => $typeLabels['expense_out'],
                    'description' => $description,
                    'in_amount' => 0,
                    'out_amount' => (string) $expense->amount,
                    'created_at' => $expense->created_at,
                ];
            });

        $transactions = $transactions->merge($sales)
            ->merge($purchasePayments)
            ->merge($customerPayments)
            ->merge($journalCashbook)
            ->merge($legacyCashbook)
            ->merge($salaryRows)
            ->merge($partyRows)
            ->merge($expenseRows)
            ->sortByDesc('created_at');

        $totalIn = '0.00';
        $totalOut = '0.00';
        foreach ($transactions as $tx) {
            $totalIn = bcadd($totalIn, (string) $tx['in_amount'], 2);
            $totalOut = bcadd($totalOut, (string) $tx['out_amount'], 2);
        }

        // Opening balance = cash in hand just before the day started, from
        // the SAME canonical union the balance sheet / wallet hero count.
        // Closing then equals the balance sheet's Cash in Hand exactly for
        // today.
        $openingBalance = number_format(
            app(CashFlowService::class)->balanceBefore($startDate->toDateString())[$currency],
            2,
            '.',
            ''
        );
        $closingBalance = bcadd($openingBalance, bcsub($totalIn, $totalOut, 2), 2);

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

        // Single day: the day's running balance opens at the opening balance
        // and lands exactly on the closing balance.
        $dailyTotals = collect($dailyTotals)->values();

        $runningBalance = $openingBalance;
        $dailyTotals = $dailyTotals->map(function ($day) use (&$runningBalance) {
            $runningBalance = bcadd($runningBalance, bcsub($day['in_total'], $day['out_total'], 2), 2);
            $day['running_balance'] = $runningBalance;

            return $day;
        });

        return view('reports.daybook', compact(
            'dailyTotals', 'anchorDate', 'selectedCurrency', 'totalIn', 'totalOut',
            'openingBalance', 'closingBalance'
        ));
    }
}
