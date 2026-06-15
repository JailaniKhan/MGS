@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">محصولات</h2>
        <a href="{{ route('products.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی محصول
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($products as $product)
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium">{{ $product->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">({{ $product->category->name }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('products.edit', $product) }}" class="text-blue-600 dark:text-blue-400 text-sm px-2 py-1">سمول</a>
                        <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 dark:text-red-400 text-sm px-2 py-1">ړنګول</button>
                        </form>
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-1 text-xs text-gray-500 dark:text-gray-400">
                    <span>قیمت: <strong>{{ number_format($product->price) }} افغ</strong></span>
                    <span>موجودي: 
                        @if ($product->stock < 10)
                            <strong class="text-red-500">{{ $product->stock }}</strong>
                        @else
                            <strong>{{ $product->stock }}</strong>
                        @endif
                        @if ($product->unit)
                            {{ $product->unit->short_name ?? $product->unit->name }}
                        @endif
                    </span>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه محصول نشته
            </div>
        @endforelse
    </div>
@endsection