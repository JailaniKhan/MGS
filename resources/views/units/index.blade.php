@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">واحدونه</h2>
        <a href="{{ route('units.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی واحد
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($units as $unit)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div>
                    <span class="text-sm font-medium">{{ $unit->name }}</span>
                    @if ($unit->short_name)
                        <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">({{ $unit->short_name }})</span>
                    @endif
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">- {{ $unit->products_count }} محصولات</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('units.edit', $unit) }}" class="text-blue-600 dark:text-blue-400 text-sm px-2 py-1">سمول</a>
                    <form action="{{ route('units.destroy', $unit) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 dark:text-red-400 text-sm px-2 py-1">ړنګول</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه واحد نشته
            </div>
        @endforelse
    </div>
@endsection