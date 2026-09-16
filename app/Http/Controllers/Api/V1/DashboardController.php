<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\CashFlowService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BalanceService $balanceService, CashFlowService $cashFlow)
    {
        $userId = $request->user()->id;

        return response()->json([
            'receivable' => [
                'AFN' => $balanceService->totalReceivable($userId, 'AFN'),
                'USD' => $balanceService->totalReceivable($userId, 'USD'),
            ],
            'payable' => [
                'AFN' => $balanceService->totalPayable($userId, 'AFN'),
                'USD' => $balanceService->totalPayable($userId, 'USD'),
            ],
            // Cash mirrors the wallet hero: the canonical cash-flow union
            // across payments, ledger payments, both cashbook generations,
            // expenses and salaries — never just the journal cash account.
            'cash' => [
                'AFN' => number_format($cashFlow->totals()['AFN']['balance'], 2, '.', ''),
                'USD' => number_format($cashFlow->totals()['USD']['balance'], 2, '.', ''),
            ],
        ]);
    }
}
