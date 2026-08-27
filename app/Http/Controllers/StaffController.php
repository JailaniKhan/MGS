<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        $employees = Employee::orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $total = Employee::count();
        $salaryRows = Employee::get(['monthly_salary', 'currency']);
        $payrollAFN = $salaryRows->where('currency', 'AFN')->sum('monthly_salary');
        $payrollUSD = $salaryRows->where('currency', 'USD')->sum('monthly_salary');

        return view('staff.index', compact('employees', 'total', 'payrollAFN', 'payrollUSD'));
    }

    public function create()
    {
        return view('staff.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'monthly_salary' => 'required|numeric|min:0',
            'currency' => 'required|in:AFN,USD',
        ]);

        Employee::create($validated);

        return redirect()->route('staff.index')->with('success', __('messages.staff_created'));
    }

    public function show(Employee $employee)
    {
        $employee->load('salaryPayments');

        return view('staff.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        return view('staff.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'monthly_salary' => 'required|numeric|min:0',
            'currency' => 'required|in:AFN,USD',
        ]);

        $employee->update($validated);

        return redirect()->route('staff.index')->with('success', __('messages.staff_updated'));
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('staff.index')->with('success', __('messages.staff_deleted'));
    }
}
