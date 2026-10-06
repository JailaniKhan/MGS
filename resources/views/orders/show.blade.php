@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                <x-icon name="clipboard-document-list" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ __('messages.order') }} #{{ $order->id }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate"><bdi>{{ local_date($order->created_at, 'Y/m/d H:i') }}</bdi></p>
            </div>
        </div>
        <x-back-button href="{{ route('orders.index') }}"/>
    </div>

    {{-- Order hero --}}
    <div class="card relative overflow-hidden p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-brand/[0.08] dark:bg-brand/[0.12]"></div>
        <div class="relative flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-2xl brand-grad text-white flex items-center justify-center flex-shrink-0 shadow-fab">
                    <span class="text-sm font-extrabold tabular-nums">#{{ $order->id }}</span>
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-ink-900 dark:text-white truncate">{{ $order->party?->name ?? __('messages.unknown') }}</h3>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.order') }} #{{ $order->id }}</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 badge flex-shrink-0
                @if($order->display_status === 'completed') badge-success
                @elseif($order->display_status === 'processing') badge-info
                @elseif($order->display_status === 'cancelled') badge-danger
                @elseif($order->display_status === 'paid') badge-success
                @else badge-warning @endif">
                <span class="status-dot
                    @if($order->display_status === 'completed') bg-primary-500
                    @elseif($order->display_status === 'processing') bg-secondary-500
                    @elseif($order->display_status === 'cancelled') bg-danger-500
                    @elseif($order->display_status === 'paid') bg-primary-500
                    @else bg-accent-500 @endif">
                </span>
                @switch($order->display_status)
                    @case('completed') {{ __('messages.completed') }} @break
                    @case('processing') {{ __('messages.processing') }} @break
                    @case('cancelled') {{ __('messages.cancelled') }} @break
                    @case('paid') {{ __('messages.paid') }} @break
                    @default {{ __('messages.pending') }}
                @endswitch
            </span>
        </div>

        <div class="relative space-y-2.5 text-sm mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                    <x-icon name="user" class="w-3.5 h-3.5 text-brand" strokeWidth="1.8"/>
                </div>
                <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.customer') }}:</span>
                @if ($order->person_type === 'customer' && $order->customer)
                    <a href="{{ route('customers.show', $order->customer) }}" class="font-bold text-brand hover:text-primary-700 dark:hover:text-primary-300 transition-colors truncate">{{ $order->party->name }}</a>
                @else
                    <span class="font-medium text-ink-800 dark:text-ink-200 truncate">{{ $order->party?->name }}</span>
                @endif
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                    <x-icon name="currency-dollar" class="w-3.5 h-3.5 text-brand" strokeWidth="1.8"/>
                </div>
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.currency') }}:</span>
                <span class="font-medium text-ink-800 dark:text-ink-200">{{ $order->currency === 'USD' ? __('messages.usd') . ' ($)' : __('messages.afn') }}</span>
            </div>
            @if ($order->party?->phone)
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                        <x-icon name="phone" class="w-3.5 h-3.5 text-brand" strokeWidth="1.8"/>
                    </div>
                    <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.phone') }}:</span>
                    <span class="text-ink-700 dark:text-ink-300 font-medium tabular-nums truncate" dir="ltr">{{ $order->party->phone }}</span>
                </div>
            @endif
            @if ($order->party?->address)
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                        <x-icon name="map-pin" class="w-3.5 h-3.5 text-brand" strokeWidth="1.8"/>
                    </div>
                    <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.address') }}:</span>
                    <span class="text-ink-700 dark:text-ink-300 truncate">{{ $order->party->address }}</span>
                </div>
            @endif
        </div>

        <div class="relative grid grid-cols-2 gap-2 pt-3 border-t border-ink-100 dark:border-ink-700/30">
            <a href="{{ route('orders.print', $order) }}" class="btn-ghost btn-sm justify-center"><x-icon name="printer" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.print') }}</a>
            <form action="{{ route('orders.pdf.save', $order) }}" method="POST">
                @csrf
                <button type="submit" class="btn-ghost btn-sm w-full justify-center"><x-icon name="arrow-down-tray" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.save_pdf') }}</button>
            </form>
            <form action="{{ route('orders.send-whatsapp', $order) }}" method="POST">
                @csrf
                <button type="submit" class="btn-primary btn-sm w-full justify-center"><x-icon name="chat-bubble-left-right" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.send_via_whatsapp') }}</button>
            </form>
            <form action="{{ route('orders.pdf.whatsapp', $order) }}" method="POST">
                @csrf
                <button type="submit" class="btn-primary btn-sm w-full justify-center"><x-icon name="paper-airplane" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.send_pdf_whatsapp') }}</button>
            </form>
        </div>
    </div>

    {{-- Order items --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="cube" class="w-4 h-4 text-brand"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.order_items') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @foreach ($order->orderItems as $item)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-brand/10 dark:bg-brand/20 text-brand flex items-center justify-center flex-shrink-0">
                            <x-icon name="cube" class="w-4 h-4" strokeWidth="1.8"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-800 dark:text-ink-200 truncate">{{ $item->product->name }}</div>
                            <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5 tabular-nums">
                                {{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif &times; <x-money :amount="$item->unit_price" :currency="$order->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                            </div>
                            @if ($item->lot_number)
                                <div class="text-[11px] text-brand font-semibold mt-0.5">{{ __('messages.lot_number') }}: {{ $item->lot_number }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="text-sm font-extrabold tabular-nums text-ink-900 dark:text-ink-100 flex-shrink-0 ms-3">
                        <x-money :amount="$item->subtotal" :currency="$order->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="px-4 py-3 bg-brand/5 dark:bg-brand/10 border-t border-ink-100 dark:border-ink-700/30">
            <div class="flex justify-between">
                <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total') }}</span>
                <span class="text-sm font-extrabold tabular-nums text-brand"><x-money :amount="$order->total_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium text-brand"/></span>
            </div>
        </div>
    </div>

    {{-- Payment summary --}}
    @if ($order->status !== 'cancelled')
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="banknotes" class="w-4 h-4 text-brand"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.payment') }}</h3>
            </div>
        </div>
        <div class="p-4">
            <div class="space-y-2.5 text-sm mb-4">
                <div class="flex justify-between">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total_amount') }}:</span>
                    <span class="font-semibold tabular-nums text-ink-900 dark:text-ink-100"><x-money :amount="$order->total_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium text-ink-500"/></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.paid') }}:</span>
                    <span class="font-semibold tabular-nums text-brand"><x-money :amount="$order->paid_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium text-brand"/></span>
                </div>
                <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-2.5">
                    <span class="font-bold text-ink-800 dark:text-ink-200">{{ __('messages.remaining') }}:</span>
                    <span class="font-extrabold tabular-nums {{ $order->is_fully_paid ? 'text-brand' : 'text-accent-600 dark:text-accent-400' }}">
                        @if ($order->is_fully_paid)
                            {{ __('messages.fully_paid') }}
                        @else
                            <x-money :amount="$order->remaining_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium"/>
                        @endif
                    </span>
                </div>
            </div>

            @if ($order->payments->count() > 0)
                <div class="border-t border-ink-100 dark:border-ink-700/30 pt-3 mb-4">
                    <p class="text-xs font-bold text-ink-500 dark:text-ink-400 mb-2">{{ __('messages.payment_history') }}:</p>
                    <div class="space-y-2">
                        @foreach ($order->payments as $payment)
                            <div class="flex justify-between items-center py-1.5 border-b border-ink-50 dark:border-ink-800/50 last:border-b-0">
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-brand"></div>
                                    <span class="text-xs text-ink-600 dark:text-ink-400 tabular-nums"><bdi>{{ local_date($payment->created_at, 'Y/m/d H:i') }}</bdi></span>
                                    @if ($payment->notes)
                                        <span class="text-xs text-ink-400 dark:text-ink-500">({{ $payment->notes }})</span>
                                    @endif
                                </div>
                                <span class="text-sm font-semibold tabular-nums text-brand"><x-money :amount="$payment->amount" :currency="$payment->currency" symbol-class="text-[10px] font-medium text-brand"/></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!$order->is_fully_paid)
                <a href="{{ route('payments.create') }}?order_id={{ $order->id }}" class="btn-primary w-full">
                    <x-icon name="plus" class="w-4 h-4" strokeWidth="2"/>
                    {{ __('messages.add_payment') }}
                </a>
            @endif
        </div>
    </div>
    @endif

    {{-- Status management --}}
    @if ($order->status !== 'completed' && $order->status !== 'cancelled')
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="flag" class="w-4 h-4 text-brand"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.change_status') }}</h3>
            </div>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 gap-2">
                <form action="{{ route('orders.status', [$order, 'pending']) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $order->status === 'pending' ? 'bg-accent-500 text-white shadow-sm' : 'bg-accent-50 dark:bg-accent-900/20 text-accent-700 dark:text-accent-300 border border-accent-200 dark:border-accent-700/30' }}">
                        <span class="status-dot {{ $order->status === 'pending' ? 'bg-white' : 'bg-accent-500' }}"></span>
                        {{ __('messages.pending') }}
                    </button>
                </form>
                <form action="{{ route('orders.status', [$order, 'processing']) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $order->status === 'processing' ? 'bg-secondary-500 text-white shadow-sm' : 'bg-secondary-50 dark:bg-secondary-900/20 text-secondary-700 dark:text-secondary-300 border border-secondary-200 dark:border-secondary-700/30' }}">
                        <span class="status-dot {{ $order->status === 'processing' ? 'bg-white' : 'bg-secondary-500' }}"></span>
                        {{ __('messages.processing') }}
                    </button>
                </form>
                <form action="{{ route('orders.status', [$order, 'completed']) }}" method="POST" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $order->status === 'completed' ? 'bg-primary-500 text-white shadow-sm' : 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-700/30' }}">
                        <span class="status-dot {{ $order->status === 'completed' ? 'bg-white' : 'bg-primary-500' }}"></span>
                        {{ __('messages.completed') }}
                    </button>
                </form>
                <form action="{{ route('orders.status', [$order, 'cancelled']) }}" method="POST" class="contents" onsubmit="return confirm('{{ __('messages.confirm_cancel') }}')">
                    @csrf
                    <button type="submit" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 active:scale-[0.97] {{ $order->status === 'cancelled' ? 'bg-danger-500 text-white shadow-sm' : 'bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-300 border border-danger-200 dark:border-danger-700/30' }}">
                        <span class="status-dot {{ $order->status === 'cancelled' ? 'bg-white' : 'bg-danger-500' }}"></span>
                        {{ __('messages.cancelled') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete order --}}
    @if ($order->status !== 'cancelled')
        <form action="{{ route('orders.destroy', $order) }}" method="POST" class="page-enter" style="animation-delay: 0.25s;" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
            @csrf @method('DELETE')
            <button class="btn-danger w-full">
                <x-icon name="trash" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.delete_order') }}
            </button>
        </form>
    @endif
@endsection
