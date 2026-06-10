@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">ګیراکان</h2>
        <a href="{{ route('customers.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی ګیراک
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($customers as $customer)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div class="flex-1">
                    <a href="{{ route('customers.show', $customer) }}" class="text-sm font-medium hover:text-[#f53003]">{{ $customer->name }}</a>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        @if ($customer->phone)<span>{{ $customer->phone }}</span>@endif
                        @if ($customer->orders_count)<span class="mr-2">| {{ $customer->orders_count }} امرونه</span>@endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('customers.edit', $customer) }}" class="text-blue-600 dark:text-blue-400 text-sm px-2 py-1">سمول</a>
                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 dark:text-red-400 text-sm px-2 py-1">ړنګول</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه ګیراک نشته
            </div>
        @endforelse
    </div>
@endsection