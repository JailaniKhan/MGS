@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">پلورونکي</h2>
        <a href="{{ route('suppliers.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی پلورونکی
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($suppliers as $supplier)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div>
                    <span class="text-sm font-medium">{{ $supplier->name }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">({{ $supplier->purchases_count }} خریدونه)</span>
                    @if ($supplier->phone)
                        <span class="text-xs text-gray-400 dark:text-gray-500 block">{{ $supplier->phone }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="text-blue-600 dark:text-blue-400 text-sm px-2 py-1">سمول</a>
                    <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 dark:text-red-400 text-sm px-2 py-1">ړنګول</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه پلورونکی نشته
            </div>
        @endforelse
    </div>
@endsection