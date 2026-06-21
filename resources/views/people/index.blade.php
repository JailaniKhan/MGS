@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">خلک</h2>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
        <button id="tab-customers" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-[#f53003] text-white" onclick="switchTab('customers')">
            ګیراکان
        </button>
        <button id="tab-suppliers" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300" onclick="switchTab('suppliers')">
            پلورونکي
        </button>
    </div>

    <!-- Customers List -->
    <div id="section-customers" class="tab-section">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">ګیراکان</h3>
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
    </div>

    <!-- Suppliers List -->
    <div id="section-suppliers" class="tab-section hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">پلورونکي</h3>
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