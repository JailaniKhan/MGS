<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Services\Accounting\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalaryController extends Controller
{
    public function store(Request $request, Employee $employee, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'for_month' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $journal = $transactionService->postSalaryPayment(
            Auth::user(),
            number_format((float) $validated['amount'], 2, '.', ''),
            $validated['currency'],
            [
                'description' => __('messages.salary_dash').$employee->name,
                'transaction_date' => now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        SalaryPayment::create([
            'employee_id' => $employee->id,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'for_month' => $validated['for_month'],
            'notes' => $validated['notes'],
            'journal_entry_id' => $journal->id,
        ]);

        return redirect()->route('staff.show', $employee)->with('success', __('messages.salary_recorded'));
    }
}
