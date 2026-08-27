@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="shopping-bag" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ __('messages.purchase') }} #{{ $purchase->id }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate"><bdi>{{ local_date($purchase->created_at, 'Y/m/d H:i') }}</bdi></p>
            </div>
        </div>
        <x-back-button href="{{ route('orders.index') }}"/>
    </div>

    {{-- Purchase hero --}}
    <div class="card relative overflow-hidden p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-accent-500/[0.08] dark:bg-accent-400/[0.1]"></div>
        <div class="relative flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-accent-500 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                    <span class="text-sm font-extrabold tabular-nums">#{{ $purchase->id }}</span>
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-ink-900 dark:text-white truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</h3>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400 tabular-nums"><bdi>{{ local_date($purchase->created_at, 'Y/m/d H:i') }}</bdi></p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 badge flex-shrink-0
                @if($purchase->display_status === 'completed') badge-success
                @elseif($purchase->display_status === 'processing') badge-info
                @elseif($purchase->display_status === 'cancelled') badge-danger
                @elseif($purchase->display_status === 'paid') badge-success
                @elseif($purchase->display_status === 'partial') badge-info
                @else badge-warning @endif">
                <span class="status-dot
                    @if($purchase->display_status === 'completed') bg-primary-500
                    @elseif($purchase->display_status === 'processing') bg-secondary-500
                    @elseif($purchase->display_status === 'cancelled') bg-danger-500
                    @elseif($purchase->display_status === 'paid') bg-primary-500
                    @elseif($purchase->display_status === 'partial') bg-secondary-500
                    @else bg-accent-500 @endif">
                </span>
                @switch($purchase->display_status)
                    @case('completed') {{ __('messages.completed') }} @break
                    @case('processing') {{ __('messages.processing') }} @break
                    @case('cancelled') {{ __('messages.cancelled') }} @break
                    @case('paid') {{ __('messages.paid') }} @break
                    @case('partial') {{ __('messages.partially_paid') }} @break
                    @default {{ __('messages.pending') }}
                @endswitch
            </span>
        </div>

        <div class="relative space-y-2.5 text-sm mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                    <x-icon name="user" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                </div>
                <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.party') }}:</span>
                <span class="font-bold text-ink-800 dark:text-ink-200 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                    <x-icon name="currency-dollar" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                </div>
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.currency') }}:</span>
                <span class="font-medium text-ink-800 dark:text-ink-200">{{ $purchase->currency === 'USD' ? __('messages.usd') . ' ($)' : __('messages.afn') }}</span>
            </div>
            @if ($purchase->party?->phone)
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                        <x-icon name="phone" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                    </div>
                    <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.phone') }}:</span>
                    <span class="text-ink-700 dark:text-ink-300 font-medium tabular-nums truncate" dir="ltr">{{ $purchase->party->phone }}</span>
                </div>
            @endif
        </div>

        <div class="relative flex gap-2 pt-3 border-t border-ink-100 dark:border-ink-700/30">
            <a href="{{ route('purchases.print', $purchase) }}" target="_blank" class="btn-ghost btn-sm flex-1 justify-center"><x-icon name="printer" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.print') }}</a>
            <form action="{{ route('purchases.send-whatsapp', $purchase) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="btn-primary btn-sm w-full justify-center"><x-icon name="chat-bubble-left-right" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.send_via_whatsapp') }}</button>
            </form>
        </div>
    </div>

    {{-- Purchase items --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="cube" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.purchase_items') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @foreach ($purchase->purchaseItems as $item)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-accent-500/10 dark:bg-accent-500/15 text-accent-600 dark:text-accent-400 flex items-center justify-center flex-shrink-0">
                            <x-icon name="cube" class="w-4 h-4" strokeWidth="1.8"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-800 dark:text-ink-200 truncate">{{ $item->product->name }}</div>
                            <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5 tabular-nums">
                                {{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif &times; <x-money :amount="$item->unit_price" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                            </div>
                            @if ($item->lot_number)
                                <div class="text-[11px] text-accent-600 dark:text-accent-400 font-semibold mt-0.5">{{ __('messages.lot_number') }}: {{ $item->lot_number }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="text-sm font-extrabold tabular-nums text-ink-900 dark:text-ink-100 flex-shrink-0 ms-3">
                        <x-money :amount="$item->subtotal" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="px-4 py-3 bg-accent-500/5 dark:bg-accent-500/10 border-t border-ink-100 dark:border-ink-700/30">
            <div class="flex justify-between">
                <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total') }}</span>
                <span class="text-sm font-extrabold tabular-nums text-accent-600 dark:text-accent-400"><x-money :amount="$purchase->total_amount" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-accent-600 dark:text-accent-400"/></span>
            </div>
        </div>
    </div>

    {{-- Payment summary --}}
    @if ($purchase->status !== 'cancelled')
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="banknotes" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.payment') }}</h3>
            </div>
        </div>
        <div class="p-4">
            <div class="space-y-2.5 text-sm mb-4">
                <div class="flex justify-between">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total_amount') }}:</span>
                    <span class="font-semibold tabular-nums text-ink-900 dark:text-ink-100"><x-money :amount="$purchase->total_amount" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.paid') }}:</span>
                    <span class="font-semibold tabular-nums text-accent-600 dark:text-accent-400"><x-money :amount="$purchase->paid_amount" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-accent-600 dark:text-accent-400"/></span>
                </div>
                <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-2.5">
                    <span class="font-bold text-ink-800 dark:text-ink-200">{{ __('messages.remaining') }}:</span>
                    <span class="font-extrabold tabular-nums {{ $purchase->is_fully_paid ? 'text-accent-600 dark:text-accent-400' : 'text-danger-500' }}">
                        @if ($purchase->is_fully_paid)
                            {{ __('messages.fully_paid') }}
                        @else
                            <x-money :amount="$purchase->remaining_amount" :currency="$purchase->currency" symbol-class="text-[10px] font-medium"/>
                        @endif
                    </span>
                </div>
            </div>

            @if ($purchase->purchasePayments->count() > 0)
                <div class="border-t border-ink-100 dark:border-ink-700/30 pt-3 mb-4">
                    <p class="text-xs font-bold text-ink-500 dark:text-ink-400 mb-2">{{ __('messages.payment_history') }}:</p>
                    <div class="space-y-2">
                        @foreach ($purchase->purchasePayments as $payment)
                            <div class="flex justify-between items-center py-1.5 border-b border-ink-50 dark:border-ink-800/50 last:border-b-0">
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-accent-500"></div>
                                    <span class="text-xs text-ink-600 dark:text-ink-400 tabular-nums"><bdi>{{ local_date($payment->created_at, 'Y/m/d H:i') }}</bdi></span>
                                    @if ($payment->notes)
                                        <span class="text-xs text-ink-400 dark:text-ink-500">({{ $payment->notes }})</span>
                                    @endif
                                </div>
                                <span class="text-sm font-semibold tabular-nums text-accent-600 dark:text-accent-400"><x-money :amount="$payment->amount" :currency="$payment->currency" symbol-class="text-[10px] font-medium text-accent-600 dark:text-accent-400"/></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!$purchase->is_fully_paid)
                <a href="{{ route('payments.create') }}?type=purchase&purchase_id={{ $purchase->id }}" class="btn-primary w-full">
                    <x-icon name="plus" class="w-4 h-4" strokeWidth="2"/>
                    {{ __('messages.add_payment') }}
                </a>
            @endif
        </div>
    </div>
    @endif

    {{-- Status management --}}
    @if ($purchase->status !== 'completed' && $purchase->status !== 'cancelled')
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="flag" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.change_status') }}</h3>
            </div>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 gap-2">
                <form action="{{ route('purchases.status', [$purchase, 'pending']) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $purchase->status === 'pending' ? 'bg-accent-500 text-white shadow-sm' : 'bg-accent-50 dark:bg-accent-900/20 text-accent-700 dark:text-accent-300 border border-accent-200 dark:border-accent-700/30' }}">
                        <span class="status-dot {{ $purchase->status === 'pending' ? 'bg-white' : 'bg-accent-500' }}"></span>
                        {{ __('messages.pending') }}
                    </button>
                </form>
                <form action="{{ route('purchases.status', [$purchase, 'processing']) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $purchase->status === 'processing' ? 'bg-secondary-500 text-white shadow-sm' : 'bg-secondary-50 dark:bg-secondary-900/20 text-secondary-700 dark:text-secondary-300 border border-secondary-200 dark:border-secondary-700/30' }}">
                        <span class="status-dot {{ $purchase->status === 'processing' ? 'bg-white' : 'bg-secondary-500' }}"></span>
                        {{ __('messages.processing') }}
                    </button>
                </form>
                <form action="{{ route('purchases.status', [$purchase, 'completed']) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $purchase->status === 'completed' ? 'bg-primary-500 text-white shadow-sm' : 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-700/30' }}">
                        <span class="status-dot {{ $purchase->status === 'completed' ? 'bg-white' : 'bg-primary-500' }}"></span>
                        {{ __('messages.completed') }}
                    </button>
                </form>
                <form action="{{ route('purchases.status', [$purchase, 'cancelled']) }}" method="POST" class="contents" onsubmit="return confirm('{{ __('messages.confirm_cancel') }}')" data-confirm-ok="{{ __('messages.cancel') }}">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $purchase->status === 'cancelled' ? 'bg-danger-500 text-white shadow-sm' : 'bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-300 border border-danger-200 dark:border-danger-700/30' }}">
                        <span class="status-dot {{ $purchase->status === 'cancelled' ? 'bg-white' : 'bg-danger-500' }}"></span>
                        {{ __('messages.cancelled') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete --}}
    @if ($purchase->status !== 'cancelled')
        <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" class="page-enter" style="animation-delay: 0.25s;" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
            @csrf @method('DELETE')
            <button class="btn-danger w-full">
                <x-icon name="trash" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.delete_purchase') }}
            </button>
        </form>
    @endif
@endsection
