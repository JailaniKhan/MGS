@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
    </div>

    <!-- Customer Header Card -->
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm">
                    <x-icon name="user" class="w-5 h-5 text-white"/>
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
                    <x-icon name="bell" class="w-3.5 h-3.5"/>{{ __('messages.remind') }}
                </a>
                <a href="{{ route('customers.edit', $customer) }}" class="btn-sm"><x-icon name="pencil" class="w-3.5 h-3.5"/>{{ __('messages.edit') }}</a>
                <a href="{{ route('ledger.show', ['customer', $customer->id]) }}" class="btn-sm"><x-icon name="document-text" class="w-3.5 h-3.5"/>{{ __('messages.ledger') }}</a>
                <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button class="btn-danger btn-sm"><x-icon name="trash" class="w-3.5 h-3.5"/>{{ __('messages.delete') }}</button>
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
        <form action="{{ route('reminders.customer', $customer) }}" method="POST" onsubmit="return submitCustomerReminder(event, '{{ $customer->id }}', '{{ $customer->phone }}')" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">{{ __('messages.channel') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-colors">
                        <input type="radio" name="channel" value="sms" checked class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">SMS</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-colors">
                        <input type="radio" name="channel" value="whatsapp" class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">WhatsApp</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-colors">
                        <input type="radio" name="currency" value="AFN" checked class="text-secondary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 transition-colors">
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
                <x-icon name="bell" class="w-4 h-4"/>{{ __('messages.send_reminder') }}
            </button>
        </form>
    </div>

    <!-- Payment Summary -->
    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="metric-tile !p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400">
                    <x-icon name="currency-dollar" class="w-4 h-4"/>
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
                    <x-icon name="currency-dollar" class="w-4 h-4"/>
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
                    <div class="text-end flex-shrink-0 ms-3">
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
                        <x-icon name="clipboard-document-list" class="w-6 h-6 text-ink-400"/>
                    </div>
                    <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_orders') }}</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const customerReminderUrl = '{{ route('reminders.customer', $customer) }}';

    function submitCustomerReminder(event, id, phone) {
        event.preventDefault();
        const form = event.target;
        const channel = form.querySelector('input[name="channel"]:checked').value;
        const currency = form.querySelector('input[name="currency"]:checked').value;
        const amount = form.querySelector('input[name="amount"]').value;

        if (channel === 'sms') {
            fetch(customerReminderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ channel: 'sms', currency: currency, amount: amount || undefined })
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                const p = data.phone.replace(/[^0-9]/g, '');
                const msg = encodeURIComponent(data.message);
                openNativeSms(p, msg);
            })
            .catch(() => { alert('Could not reach the server. Please try again.'); });
        } else {
            const ov = document.getElementById('page-skeleton');
            if (ov) ov.hidden = false;
            form.submit();
        }
        return false;
    }

    function openNativeSms(phone, encodedMsg) {
        const smsUrl = 'sms:' + phone + '?body=' + encodedMsg;
        const before = window.location.href;
        window.location.href = smsUrl;
        setTimeout(() => {
            if (window.location.href === before) {
                prompt('SMS not supported in this browser. Copy the message below to send it manually:', decodeURIComponent(encodedMsg));
            }
        }, 600);
    }
</script>
@endpush
