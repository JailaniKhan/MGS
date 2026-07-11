<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Accounting\BalanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BalanceService $balanceService)
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
            'cash' => [
                'AFN' => $balanceService->cashBalance($userId, 'AFN'),
                'USD' => $balanceService->cashBalance($userId, 'USD'),
            ],
        ]);
    }
}
