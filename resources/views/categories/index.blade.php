@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">کتګورۍ</h2>
        <a href="{{ route('categories.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی کتګوري
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($categories as $category)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div>
                    <span class="text-sm font-medium">{{ $category->name }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">({{ $category->products_count }} محصولات)</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('categories.edit', $category) }}" class="text-blue-600 dark:text-blue-400 text-sm px-2 py-1">سمول</a>
                    <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 dark:text-red-400 text-sm px-2 py-1">ړنګول</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه کتګوري نشته
            </div>
        @endforelse
    </div>
@endsection