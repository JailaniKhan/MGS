<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::latest('expense_date')->paginate(self::PER_PAGE)->withQueryString();
        return view('expenses.index', compact('expenses'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->pluck('name');
        return view('expenses.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'receipt_path' => 'nullable|string|max:500',
        ]);

        Expense::create(array_merge($validated, ['user_id' => Auth::id()]));

        return redirect()->route('expenses.index')->with('success', __('messages.expense_created'));
    }

    public function show(Expense $expense)
    {
        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        $categories = Category::orderBy('name')->pluck('name');
        return view('expenses.edit', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'receipt_path' => 'nullable|string|max:500',
        ]);

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('success', __('messages.expense_updated'));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', __('messages.expense_deleted'));
    }
}