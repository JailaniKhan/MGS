<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount('orders')->orderBy('name')->get();
        $suppliers = Supplier::withCount('purchases')->orderBy('name')->get();
        return view('people.index', compact('customers', 'suppliers'));
    }
}