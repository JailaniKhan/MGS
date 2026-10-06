@extends('layouts.app')

@section('content')
@php
    $tileStyles = [
        'bg-brand/10 dark:bg-brand/20 text-brand',
        'bg-secondary-500/10 dark:bg-secondary-500/15 text-secondary-600 dark:text-secondary-400',
        'bg-accent-500/10 dark:bg-accent-500/15 text-accent-600 dark:text-accent-400',
    ];
    $initials = mb_strtoupper(mb_substr($customer->name, 0, 1));
    $avatarClass = ['bg-brand', 'bg-secondary-500', 'bg-accent-500'][crc32($customer->name) % 3];
    $remainingAFN = $remainingAFN ?? max(0, $totalAFN - $paidAFN);
    $remainingUSD = $remainingUSD ?? max(0, $totalUSD - $paidUSD);
@endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0 {{ $avatarClass }}">
                {{ $initials }}
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ $customer->name }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.customer') }} &middot; {{ $customer->orders->count() }} {{ __('messages.orders') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <x-icon-button name="bell" label="{{ __('messages.remind') }}"
                           onclick="document.getElementById('reminder-panel').classList.toggle('hidden')"/>
            <x-back-button href="{{ route('people.index') }}"/>
        </div>
    </div>

    {{-- Balance summary --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-brand/[0.08] dark:bg-brand/[0.12]"></div>
        <div class="relative">
            <span class="metric-label">{{ __('messages.remaining') }}</span>
            <div class="mt-1 flex items-end gap-4 flex-wrap">
                <p class="text-3xl font-extrabold tabular-nums tracking-tight {{ $remainingAFN > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}" dir="ltr">
                    {{ number_format($remainingAFN) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span>
                </p>
                <p class="text-3xl font-extrabold tabular-nums tracking-tight {{ $remainingUSD > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}" dir="ltr">
                    {{ number_format($remainingUSD) }}<span class="text-sm font-bold text-ink-400 dark:text-ink-500">$</span>
                </p>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] tabular-nums">
                <div>
                    <span class="text-[9px] font-bold text-ink-400 uppercase tracking-wider">{{ __('messages.afn') }}</span>
                    <div class="mt-1 flex items-center gap-4">
                        <div>
                            <span class="text-ink-400">{{ __('messages.total') }}</span>
                            <p class="font-bold text-ink-700 dark:text-ink-300">{{ number_format($totalAFN) }}</p>
                        </div>
                        <div>
                            <span class="text-ink-400">{{ __('messages.paid') }}</span>
                            <p class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($paidAFN) }}</p>
                        </div>
                    </div>
                </div>
                <div>
                    <span class="text-[9px] font-bold text-ink-400 uppercase tracking-wider">{{ __('messages.usd') }}</span>
                    <div class="mt-1 flex items-center gap-4">
                        <div>
                            <span class="text-ink-400">{{ __('messages.total') }}</span>
                            <p class="font-bold text-ink-700 dark:text-ink-300">{{ number_format($totalUSD) }}$</p>
                        </div>
                        <div>
                            <span class="text-ink-400">{{ __('messages.paid') }}</span>
                            <p class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($paidUSD) }}$</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button type="button" onclick="document.getElementById('reminder-panel').classList.toggle('hidden')" class="btn-primary btn-sm">
                    <x-icon name="bell" class="w-3.5 h-3.5" strokeWidth="2"/>
                    {{ __('messages.remind') }}
                </button>
                @if ($customer->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $customer->phone) }}" class="btn-ghost btn-sm" dir="ltr">
                        <x-icon name="phone" class="w-3.5 h-3.5" strokeWidth="2"/>
                        {{ __('messages.call') }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Send reminder (collapsed by default) --}}
    <div id="reminder-panel" class="card overflow-hidden mb-4 hidden page-enter">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <x-icon name="bell" class="w-4 h-4 text-brand"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.send_reminder') }}</h3>
            </div>
        </div>
        <form action="{{ route('reminders.customer', $customer) }}" method="POST" onsubmit="return submitCustomerReminder(event, '{{ $customer->id }}', '{{ $customer->phone }}')" class="p-4">
            @csrf
            <div class="mb-3">
                <label class="form-label">{{ __('messages.channel') }}</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-ink-200 dark:border-ink-700 cursor-pointer transition-colors has-[:checked]:border-brand has-[:checked]:bg-brand/10">
                        <input type="radio" name="channel" value="sms" checked class="hidden">
                        <x-icon name="device-phone-mobile" class="w-4 h-4 text-ink-500 dark:text-ink-400"/>
                        <span class="text-sm font-medium text-ink-700 dark:text-ink-300">SMS</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-ink-200 dark:border-ink-700 cursor-pointer transition-colors has-[:checked]:border-primary-500 has-[:checked]:bg-primary-500/10">
                        <input type="radio" name="channel" value="whatsapp" class="hidden">
                        <x-icon name="chat-bubble-left-right" class="w-4 h-4 text-ink-500 dark:text-ink-400"/>
                        <span class="text-sm font-medium text-ink-700 dark:text-ink-300">WhatsApp</span>
                    </label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-ink-200 dark:border-ink-700 cursor-pointer transition-colors has-[:checked]:border-brand has-[:checked]:bg-brand/10">
                        <input type="radio" name="currency" value="AFN" checked class="hidden">
                        <span class="text-sm font-bold text-ink-700 dark:text-ink-300">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-ink-200 dark:border-ink-700 cursor-pointer transition-colors has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-500/10">
                        <input type="radio" name="currency" value="USD" class="hidden">
                        <span class="text-sm font-bold text-ink-700 dark:text-ink-300">{{ __('messages.usd') }}</span>
                    </label>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.amount') }} ({{ __('messages.optional') }})</label>
                <input type="number" name="amount" step="0.01" min="0" placeholder="{{ __('messages.optional') }}" dir="ltr" inputmode="decimal" class="form-input text-center font-bold tabular-nums">
                <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.leave_empty_for_full') }}</p>
            </div>
            <button type="submit" class="btn-primary w-full">
                <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.send_reminder') }}
            </button>
        </form>
    </div>

    {{-- Details --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <x-icon name="identification" class="w-4 h-4 text-brand"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.details') }}</h3>
                <div class="ms-auto flex items-center gap-1.5">
                    @if ($customer->phone)
                        <a href="{{ route('whatsapp.chats.show', ['customer', $customer->id]) }}" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:brightness-105 transition-all active:scale-95" aria-label="{{ __('messages.wa_open_chat') }}">
                            <x-icon name="chat-bubble-left-right" class="w-3.5 h-3.5" strokeWidth="1.8"/>
                        </a>
                    @endif
                    <a href="{{ route('customers.edit', $customer) }}" class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.06] border border-ink-200 dark:border-white/[0.08] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 transition-all active:scale-95" aria-label="{{ __('messages.edit') }}">
                        <x-icon name="pencil-square" class="w-3.5 h-3.5" strokeWidth="1.8"/>
                    </a>
                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')" class="inline">
                        @csrf @method('DELETE')
                        <button class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.06] border border-ink-200 dark:border-white/[0.08] flex items-center justify-center text-danger-500 hover:text-danger-600 transition-all active:scale-95" aria-label="{{ __('messages.delete') }}">
                            <x-icon name="trash" class="w-3.5 h-3.5" strokeWidth="1.8"/>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            <div class="flex items-center gap-3 px-4 py-3">
                <x-icon name="phone" class="w-4 h-4 text-ink-400 flex-shrink-0" strokeWidth="1.8"/>
                <div class="min-w-0 flex-1">
                    <span class="text-[10px] font-bold text-ink-400 uppercase tracking-wider">{{ __('messages.phone') }}</span>
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-100" dir="ltr">{{ $customer->phone ?: '—' }}</p>
                </div>
                <a href="{{ route('ledger.show', ['customer', $customer->id]) }}" class="text-xs font-semibold text-brand flex items-center gap-1 flex-shrink-0">
                    <x-icon name="document-text" class="w-3.5 h-3.5" strokeWidth="1.8"/>
                    {{ __('messages.ledger') }}
                </a>
            </div>
            <div class="flex items-center gap-3 px-4 py-3">
                <x-icon name="map-pin" class="w-4 h-4 text-ink-400 flex-shrink-0" strokeWidth="1.8"/>
                <div class="min-w-0">
                    <span class="text-[10px] font-bold text-ink-400 uppercase tracking-wider">{{ __('messages.address') }}</span>
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-100 break-words">{{ $customer->address ?: '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Orders --}}
    <div class="flex items-center justify-between mb-3 page-enter" style="animation-delay: 0.15s;">
        <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.orders') }} ({{ $customer->orders->count() }})</span>
        <a href="{{ route('orders.create') }}" class="btn-ghost btn-sm">
            <x-icon name="plus" class="w-3 h-3" strokeWidth="2"/>
            {{ __('messages.new_order') }}
        </a>
    </div>
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.18s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($customer->orders as $order)
                @php $tile = $tileStyles[crc32((string) $order->id) % count($tileStyles)]; @endphp
                <a href="{{ route('orders.show', $order) }}" class="list-row block active:bg-ink-50 dark:active:bg-white/[0.03] transition-colors">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl {{ $tile }} flex items-center justify-center flex-shrink-0">
                            <x-icon name="document-text" class="w-4 h-4" strokeWidth="1.8"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ __('messages.order') }} #{{ $order->id }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400 tabular-nums"><bdi>{{ local_date($order->created_at, 'Y/m/d H:i') }}</bdi></div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-extrabold tabular-nums text-ink-900 dark:text-ink-100">
                            <x-money :amount="$order->total_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                        </div>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full mt-1 border
                            @if($order->display_status === 'paid' || $order->display_status === 'completed') bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20
                            @elseif($order->display_status === 'processing') bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 border-secondary-500/20
                            @elseif($order->display_status === 'cancelled') bg-danger-50 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400 border-danger-200 dark:border-danger-700/50
                            @else bg-accent-500/10 text-accent-600 dark:text-accent-400 border-accent-500/20 @endif">
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
                <x-empty-state title="{{ __('messages.no_orders') }}">
                    <x-icon name="clipboard-document-list" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
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
                if (data.error) { showToast('error', data.error); return; }
                const p = data.phone.replace(/[^0-9]/g, '');
                const msg = encodeURIComponent(data.message);
                openNativeSms(p, msg);
            })
            .catch(() => { showToast('error', {{ __('messages.reminder_failed') }}); });
        } else {
            const ov = document.getElementById('page-skeleton');
            if (ov) ov.hidden = false;
            // mgsSubmitForm keeps the platform's submit listeners firing so
            // the NativePHP bridge can capture _token/_method (a bare
            // form.submit() reaches Laravel with an empty body on device).
            if (window.mgsSubmitForm) { window.mgsSubmitForm(form); } else { form.submit(); }
        }
        return false;
    }

    function openNativeSms(phone, encodedMsg) {
        const smsUrl = 'sms:' + phone + '?body=' + encodedMsg;
        const before = window.location.href;
        window.location.href = smsUrl;
        setTimeout(() => {
            if (window.location.href === before) {
                const msg = decodeURIComponent(encodedMsg);
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(msg)
                        .then(() => showToast('success', @js(__('messages.sms_copied'))))
                        .catch(() => showToast('error', @js(__('messages.sms_copy_failed'))));
                } else {
                    showToast('error', @js(__('messages.sms_copy_failed')));
                }
            }
        }, 600);
    }
</script>
@endpush
