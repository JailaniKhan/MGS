@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">زېرمه</h2>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
        <button id="tab-products" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-[#f53003] text-white" onclick="switchTab('products')">
            محصولات
        </button>
        <button id="tab-categories" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300" onclick="switchTab('categories')">
            کتګورۍ
        </button>
        <button id="tab-units" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300" onclick="switchTab('units')">
            واحدونه
        </button>
    </div>

    <!-- Products List -->
    <div id="section-products" class="tab-section">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">محصولات</h3>
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
    </div>

    <!-- Categories List -->
    <div id="section-categories" class="tab-section hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">کتګورۍ</h3>
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
    </div>

    <!-- Units List -->
    <div id="section-units" class="tab-section hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">واحدونه</h3>
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
    </div>
@endsection

@push('scripts')
<script>
    function switchTab(tab) {
        // Hide all sections
        document.querySelectorAll('.tab-section').forEach(el => el.classList.add('hidden'));
        // Show selected section
        document.getElementById('section-' + tab).classList.remove('hidden');

        // Update button styles
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('bg-[#f53003]', 'text-white');
            el.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-600', 'dark:text-gray-300');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-600', 'dark:text-gray-300');
        activeBtn.classList.add('bg-[#f53003]', 'text-white');
    }
</script>
@endpush