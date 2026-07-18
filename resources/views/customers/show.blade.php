@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
    </div>

    <!-- Customer Header Card -->
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ $customer->name }}</h2>
                    @if ($customer->phone)
                        <p class="text-sm text-ink-500 dark:text-ink-400 mt-0.5">{{ __('messages.phone') }}: {{ $customer->phone }}</p>
                    @endif
                    @if ($customer->address)
                        <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.address') }}: {{ $customer->address }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reminders.customer', $customer) }}" onclick="event.preventDefault(); document.getElementById('reminder-form-{{ $customer->id }}').classList.toggle('hidden')" class="btn-sm !text-secondary-600 !border-secondary-200 !bg-secondary-50 dark:!bg-secondary-900/20 dark:!border-secondary-800/30">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>{{ __('messages.remind') }}
                </a>
                <a href="{{ route('customers.edit', $customer) }}" class="btn-sm"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>{{ __('messages.edit') }}</a>
                <a href="{{ route('ledger.show', ['customer', $customer->id]) }}" class="btn-sm"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>{{ __('messages.ledger') }}</a>
                <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button class="btn-danger btn-sm"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>{{ __('messages.delete') }}</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Reminder Form -->
    <div id="reminder-form-{{ $customer->id }}" class="card p-4 mb-4 hidden page-enter" style="animation-delay: 0.08s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.send_reminder') }}</h3>
        </div>
        <form action="{{ route('reminders.customer', $customer) }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">{{ __('messages.channel') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-all duration-200">
                        <input type="radio" name="channel" value="sms" checked class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">SMS</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-all duration-200">
                        <input type="radio" name="channel" value="whatsapp" class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">WhatsApp</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="AFN" checked class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="USD" class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.usd_with_paren') }}$)</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="form-label">{{ __('messages.amount') }}</label>
                <input type="number" name="amount" step="0.01" min="0" placeholder="{{ __('messages.optional') }}" class="form-input">
                <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.leave_empty_for_full') }}</p>
            </div>
            <button type="submit" class="btn-primary w-full">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>{{ __('messages.send_reminder') }}
            </button>
        </form>
    </div>

    <!-- Payment Summary -->
    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="metric-tile !p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-medium text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.afn') }}</span>
            </div>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total') }}:</span>
                    <span class="font-medium text-ink-900 dark:text-ink-100">{{ number_format($totalAFN) }} {{ __('messages.afn') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-primary-600 dark:text-primary-400">{{ __('messages.paid') }}:</span>
                    <span class="font-medium text-primary-600 dark:text-primary-400">{{ number_format($paidAFN) }} {{ __('messages.afn') }}</span>
                </div>
                <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-1.5">
                    <span class="font-semibold text-ink-700 dark:text-ink-300">{{ __('messages.remaining') }}:</span>
                    <span class="font-bold {{ $totalAFN - $paidAFN > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}">
                        {{ number_format(max(0, $totalAFN - $paidAFN)) }} {{ __('messages.afn') }}
                    </span>
                </div>
            </div>
        </div>
        <div class="metric-tile !p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-secondary-100 dark:bg-secondary-900/30 flex items-center justify-center text-secondary-600 dark:text-secondary-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-medium text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.usd_with_paren') }}$)</span>
            </div>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total') }}:</span>
                    <span class="font-medium text-ink-900 dark:text-ink-100">{{ number_format($totalUSD) }}$</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-primary-600 dark:text-primary-400">{{ __('messages.paid') }}:</span>
                    <span class="font-medium text-primary-600 dark:text-primary-400">{{ number_format($paidUSD) }}$</span>
                </div>
                <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-1.5">
                    <span class="font-semibold text-ink-700 dark:text-ink-300">{{ __('messages.remaining') }}:</span>
                    <span class="font-bold {{ $totalUSD - $paidUSD > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}">
                        {{ number_format(max(0, $totalUSD - $paidUSD)) }}$
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders -->
    <div class="flex items-center gap-2 mb-3 page-enter" style="animation-delay: 0.15s;">
        <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
        <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.orders') }}</h3>
    </div>
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($customer->orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-4 py-3.5 transition-all duration-200 hover:bg-ink-50 dark:hover:bg-white/5">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                            <span class="text-white font-bold text-xs">#{{ $order->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs text-ink-500 dark:text-ink-400">{{ $order->created_at->format('Y/m/d') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                        <span class="inline-flex items-center gap-1 badge mt-1
                            @if($order->display_status === 'paid' || $order->display_status === 'completed') badge-success
                            @elseif($order->display_status === 'processing') badge-info
                            @elseif($order->display_status === 'cancelled') badge-danger
                            @else badge-warning @endif">
                            <span class="status-dot
                                @if($order->display_status === 'paid' || $order->display_status === 'completed') bg-primary-500
                                @elseif($order->display_status === 'processing') bg-secondary-500
                                @elseif($order->display_status === 'cancelled') bg-danger-500
                                @else bg-accent-500 @endif">
                            </span>
                            @switch($order->display_status)
                                @case('paid') {{ __('messages.paid') }} @break
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
                                @default {{ __('messages.pending') }}
                            @endswitch
                        </span>
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <div class="w-12 h-12 rounded-full bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                    </div>
                    <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_orders') }}</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection