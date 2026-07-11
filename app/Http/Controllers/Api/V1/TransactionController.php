<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\Accounting\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = JournalEntry::query()->with('ledgerEntries.account')->latest('transaction_date');

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        return JournalEntryResource::collection($query->paginate(50));
    }

    public function store(Request $request, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'idempotency_key' => 'nullable|uuid',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
            'currency' => 'required|in:AFN,USD',
            'lines' => 'required|array|min:2',
            'lines.*.account_uuid' => 'required|uuid|exists:accounts,uuid',
            'lines.*.direction' => 'required|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        $journal = $transactionService->postFromSync($request->user(), $validated['lines'], [
            'idempotency_key' => $validated['idempotency_key'] ?? null,
            'description' => $validated['description'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'currency' => $validated['currency'],
            'source' => 'api',
        ]);

        return new JournalEntryResource($journal);
    }
}
