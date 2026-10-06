@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('purchases.returns.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center border border-accent-100 dark:border-accent-800/40">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4 text-white"/>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.purchase_return') }} #{{ $purchaseReturn->id }}</h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.purchase') }} #{{ $purchaseReturn->purchase_id }}</p>
                </div>
            </div>
            <span class="badge @if($purchaseReturn->status === 'completed') badge-success @elseif($purchaseReturn->status === 'processing') badge-info @elseif($purchaseReturn->status === 'cancelled') badge-danger @else badge-warning @endif">
                <span class="status-dot @if($purchaseReturn->status === 'completed') bg-primary-500 @elseif($purchaseReturn->status === 'processing') bg-secondary-500 @elseif($purchaseReturn->status === 'cancelled') bg-danger-500 @else bg-accent-500 @endif"></span>
                @switch($purchaseReturn->status) @case('completed') {{ __('messages.completed') }} @break @case('processing') {{ __('messages.processing') }} @break @case('cancelled') {{ __('messages.cancelled') }} @break @default {{ __('messages.pending') }} @endswitch
            </span>
        </div>
        <div class="space-y-1.5 text-sm">
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.party') }}: </span><span class="font-medium text-ink-800 dark:text-ink-200">{{ $purchaseReturn->purchase->party?->name ?? __('messages.unknown') }}</span></div>
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.return_date') }}: </span><span class="font-medium"><bdi>{{ local_date($purchaseReturn->return_date, 'Y/m/d') }}</bdi></span></div>
            @if ($purchaseReturn->reason)<div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.reason') }}: </span><span class="font-medium">{{ $purchaseReturn->reason }}</span></div>@endif
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.currency_unit') }}: </span><span class="font-medium">{{ $purchaseReturn->purchase->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afghani_with_paren') . 'AFN)' }}</span></div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.return_items') }}</h3>
            </div>
        </div>
        @foreach ($purchaseReturn->items as $item)
            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200">{{ $item->product->name }}</div>
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif x <x-money :amount="$item->unit_price" :currency="$purchaseReturn->purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/></div>
                </div>
                <div class="text-sm font-semibold text-ink-900 dark:text-ink-100"><x-money :amount="$item->subtotal" :currency="$purchaseReturn->purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/></div>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-4 py-3 bg-primary-50 dark:bg-primary-900/10 font-bold">
            <span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total') }}</span>
            <span class="text-sm text-primary-700 dark:text-primary-300"><x-money :amount="$purchaseReturn->total_amount" :currency="$purchaseReturn->purchase->currency" symbol-class="text-[10px] font-medium text-primary-700 dark:text-primary-300"/></span>
        </div>
    </div>

    @if ($purchaseReturn->status !== 'cancelled')
        <form action="{{ route('purchases.returns.destroy', $purchaseReturn) }}" method="POST" class="page-enter" style="animation-delay: 0.15s;" onsubmit="return confirm('{{ __('messages.confirm_delete_return') }}')">
            @csrf @method('DELETE')
            <button class="btn-danger w-full"><x-icon name="trash" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.delete_return') }}</button>
        </form>
    @endif
@endsection