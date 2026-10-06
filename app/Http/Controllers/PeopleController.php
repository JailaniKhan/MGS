<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;

class PeopleController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount('orders')
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'customers_page')
            ->withQueryString();

        $suppliers = Supplier::withCount('purchases')
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'suppliers_page')
            ->withQueryString();

        return view('people.index', compact('customers', 'suppliers'));
    }
}
