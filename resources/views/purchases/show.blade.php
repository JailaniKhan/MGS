@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
    </div>

    <!-- Purchase Header Card -->
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex justify-between items-start mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center shadow-sm">
                    <x-icon name="shopping-bag" class="w-4 h-4 text-white"/>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.purchase') }} #{{ $purchase->id }}</h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">{{ $purchase->created_at->format('Y/m/d H:i') }}</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 badge
                @if($purchase->display_status === 'completed') badge-success
                @elseif($purchase->display_status === 'processing') badge-info
                @elseif($purchase->display_status === 'cancelled') badge-danger
                @elseif($purchase->display_status === 'paid') badge-success
                @else badge-warning @endif">
                <span class="status-dot
                    @if($purchase->display_status === 'completed') bg-primary-500
                    @elseif($purchase->display_status === 'processing') bg-secondary-500
                    @elseif($purchase->display_status === 'cancelled') bg-danger-500
                    @elseif($purchase->display_status === 'paid') bg-primary-500
                    @else bg-accent-500 @endif">
                </span>
                @switch($purchase->display_status)
                    @case('completed') {{ __('messages.completed') }} @break
                    @case('processing') {{ __('messages.processing') }} @break
                    @case('cancelled') {{ __('messages.cancelled') }} @break
                    @case('paid') {{ __('messages.paid') }} @break
                    @default {{ __('messages.pending') }}
                @endswitch
            </span>
        </div>

        <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.party') }}:</span>
                <span class="font-medium text-ink-800 dark:text-ink-200">{{ $purchase->party?->name ?? __('messages.unknown') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.currency') }}:</span>
                <span class="font-medium">{{ $purchase->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afn') . ' (' . __('messages.afn') . ')' }}</span>
            </div>
            @if ($purchase->party?->phone)
                <div class="flex items-center gap-2">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.phone') }}:</span>
                    <span class="text-ink-700 dark:text-ink-300">{{ $purchase->party->phone }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Purchase Items -->
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.purchase_items') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @foreach ($purchase->purchaseItems as $item)
                <div class="flex items-center justify-between px-4 py-3">
                    <div>
                        <div class="text-sm font-medium text-ink-800 dark:text-ink-200">{{ $item->product->name }}</div>
                        <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">
                            {{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif x {{ number_format($item->unit_price) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}
                        </div>
                        @if ($item->lot_number)
                            <div class="text-[11px] text-primary-600 dark:text-primary-400 mt-0.5 font-medium">{{ __('messages.lot_number') }}: {{ $item->lot_number }}</div>
                        @endif
                    </div>
                    <div class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ number_format($item->subtotal) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                </div>
            @endforeach
        </div>
        @if ($purchase->tax_rate > 0)
        <div class="px-4 py-2.5 bg-ink-50 dark:bg-ink-800/50 border-t border-ink-100 dark:border-ink-700/30">
            <div class="flex justify-between text-xs">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.subtotal') }} ({{ __('messages.without_tax') }})</span>
                <span class="font-medium text-ink-700 dark:text-ink-300">{{ number_format($purchase->subtotal) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </div>
            <div class="flex justify-between text-xs mt-1">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.tax') }} ({{ $purchase->tax_rate }}% {{ $purchase->tax_type === 'inclusive' ? __('messages.inclusive') : __('messages.exclusive') }})</span>
                <span class="font-medium text-ink-700 dark:text-ink-300">{{ number_format($purchase->tax_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </div>
        </div>
        @endif
        <div class="px-4 py-3 bg-primary-50 dark:bg-primary-900/10 border-t border-ink-100 dark:border-ink-700/30">
            <div class="flex justify-between">
                <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total') }}</span>
                <span class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </div>
        </div>
    </div>

    <!-- Payment Summary -->
    @if ($purchase->status !== 'cancelled')
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.payment') }}</h3>
        </div>
        <div class="space-y-2.5 text-sm mb-4">
            <div class="flex justify-between">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total_amount') }}:</span>
                <span class="font-medium text-ink-900 dark:text-ink-100">{{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.paid') }}:</span>
                <span class="font-medium text-primary-600 dark:text-primary-400">{{ number_format($purchase->paid_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </div>
            <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-2.5">
                <span class="font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.remaining') }}:</span>
                <span class="font-bold {{ $purchase->is_fully_paid ? 'text-primary-600 dark:text-primary-400' : 'text-accent-600 dark:text-accent-400' }}">
                    {{ $purchase->is_fully_paid ? __('messages.fully_paid') : number_format($purchase->remaining_amount) . ' ' . ($purchase->currency === 'USD' ? '$' : __('messages.afn')) }}
                </span>
            </div>
        </div>

        @if ($purchase->purchasePayments->count() > 0)
            <div class="border-t border-ink-100 dark:border-ink-700/30 pt-3 mb-3">
                <p class="text-xs font-medium text-ink-500 dark:text-ink-400 mb-2">{{ __('messages.payment_history') }}:</p>
                <div class="space-y-2">
                    @foreach ($purchase->purchasePayments as $payment)
                        <div class="flex justify-between items-center py-1.5 border-b border-ink-50 dark:border-ink-800/50 last:border-b-0">
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-primary-500"></div>
                                <span class="text-xs text-ink-600 dark:text-ink-400">{{ $payment->created_at->format('Y/m/d H:i') }}</span>
                                @if ($payment->notes)
                                    <span class="text-xs text-ink-400 dark:text-ink-500">({{ $payment->notes }})</span>
                                @endif
                            </div>
                            <span class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ number_format($payment->amount) }} {{ $payment->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (!$purchase->is_fully_paid)
            <form action="{{ route('purchases.payment.store') }}" method="POST" class="border-t border-ink-100 dark:border-ink-700/30 pt-4">
                @csrf
                <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
                <input type="hidden" name="currency" value="{{ $purchase->currency }}">
                <div class="mb-3">
                    <label class="form-label !text-xs">{{ __('messages.amount') }}</label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $purchase->remaining_amount }}" required placeholder="{{ __('messages.amount') }}" class="form-input">
                </div>
                <div class="mb-3">
                    <label class="form-label !text-xs">{{ __('messages.notes') }} ({{ __('messages.optional') }})</label>
                    <input type="text" name="notes" placeholder="{{ __('messages.notes') }}" class="form-input">
                </div>
                <button type="submit" class="btn-primary w-full py-3">
                    <x-icon name="plus" class="w-4 h-4" strokeWidth="2"/>
                    {{ __('messages.add_payment') }}
                </button>
            </form>
        @endif
    </div>
    @endif

    <!-- Status Management -->
    @if ($purchase->status !== 'completed' && $purchase->status !== 'cancelled')
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.change_status') }}</h3>
        </div>
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
    @endif

    <!-- Delete -->
    @if ($purchase->status !== 'cancelled')
        <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" class="mt-4 page-enter" style="animation-delay: 0.25s;" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
            @csrf @method('DELETE')
            <button class="btn-danger w-full py-3.5">
                <x-icon name="trash" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.delete_purchase') }}
            </button>
        </form>
    @endif
@endsection