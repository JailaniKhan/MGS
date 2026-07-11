<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Accounting\BalanceService;
use App\Services\Sms\SmsGateway;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function reminder(Request $request, BalanceService $balanceService, SmsGateway $smsGateway)
    {
        $validated = $request->validate([
            'account_uuid' => 'required|uuid|exists:accounts,uuid',
        ]);

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();

        if (! $account->phone) {
            return response()->json(['message' => __('messages.no_phone_for_account')], 422);
        }

        $balance = $balanceService->balance($account->id);

        if (bccomp($balance, '0', 2) !== 1) {
            return response()->json(['message' => __('messages.no_debt_for_account')], 422);
        }

        $message = __('messages.dear') . ' ' . $account->name . __('messages.your_remaining_debt') . ' ' . $balance . ' ' . $account->currency . ' ' . __('messages.please_pay_soon');

        $smsGateway->send($account->phone, $message);

        return response()->json(['message' => 'SMS ' . __('messages.reminder_sent')]);
    }
}
