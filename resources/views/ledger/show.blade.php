@extends('layouts.app')

@section('content')
    @php
        $isCustomer = $personType === 'customer';
        $initial = mb_substr(trim($person->name), 0, 1);
        $remainingAFN = (float) $summary['remaining_afn'];
        $remainingUSD = (float) $summary['remaining_usd'];
        $creditAFN = (float) $summary['credit_afn'];
        $creditUSD = (float) $summary['credit_usd'];
        $hasBalance = $remainingAFN > 0 || $remainingUSD > 0;
        $hasCredit = $creditAFN > 0 || $creditUSD > 0;
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] {{ $isCustomer ? 'bg-primary-50 dark:bg-primary-900/25 border border-primary-100 dark:border-primary-800/40' : 'bg-danger-50 dark:bg-danger-900/25 border border-danger-100 dark:border-danger-800/40' }} flex items-center justify-center flex-shrink-0">
                <x-icon name="{{ $isCustomer ? 'identification' : 'truck' }}" class="w-4 h-4 {{ $isCustomer ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500 dark:text-danger-400' }}" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ $person->name }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $personLabel }} &middot; {{ $summary['total_documents'] }} {{ __('messages.documents') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('ledger.index') }}"/>
    </div>

    {{-- Person card --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-36 h-36 rounded-full {{ $isCustomer ? 'bg-primary-500/[0.07] dark:bg-primary-400/[0.08]' : 'bg-danger-500/[0.07] dark:bg-danger-400/[0.08]' }}"></div>
        <div class="relative flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl {{ $isCustomer ? 'bg-primary-500 dark:bg-primary-600' : 'bg-danger-500 dark:bg-danger-600' }} text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                <span class="text-lg font-extrabold">{{ $initial }}</span>
            </div>
            <div class="min-w-0 flex-1">
                @if ($person->phone)
                    <p class="flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400" dir="ltr">
                        <x-icon name="device-phone-mobile" class="w-3.5 h-3.5 flex-shrink-0"/>
                        {{ $person->phone }}
                    </p>
                @endif
                @if ($person->address)
                    <p class="flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400 mt-0.5">
                        <x-icon name="map-pin" class="w-3.5 h-3.5 flex-shrink-0"/>
                        <span class="truncate">{{ $person->address }}</span>
                    </p>
                @endif
                <p class="mt-1.5">
                    @if ($hasBalance)
                        <span class="badge {{ $isCustomer ? 'badge-warning' : 'badge-danger' }}">
                            <x-icon name="clock" class="w-3.5 h-3.5"/>{{ __('messages.pending') }}
                        </span>
                    @elseif ($hasCredit)
                        <span class="badge badge-success">
                            <x-icon name="wallet" class="w-3.5 h-3.5"/>{{ __('messages.credit') }}
                        </span>
                    @else
                        <span class="badge badge-success">
                            <x-icon name="check-circle" class="w-3.5 h-3.5"/>{{ __('messages.fully_paid') }}
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
                <x-icon-button name="bell" strokeWidth="1.8" tone="secondary" label="{{ __('messages.send_reminder') }}"
                               onclick="document.getElementById('reminder-form-ledger').classList.toggle('hidden')"/>
                <x-icon-button name="document-arrow-down" strokeWidth="1.8" href="{{ route('ledger.pdf', [$personType, $person->id]) }}"
                               label="{{ __('messages.download_pdf') }}"/>
            </div>
        </div>
    </div>

    <!-- Send Reminder Form (toggled) -->
    <div id="reminder-form-ledger" class="card p-4 mb-4 hidden page-enter" style="animation-delay: 0.08s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1 h-4 rounded-full bg-secondary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.send_reminder') }}</h3>
        </div>
        <form action="{{ $personType === 'customer' ? route('reminders.customer', $person) : route('reminders.supplier', $person) }}"
              method="POST"
              onsubmit="return submitLedgerReminder(event, '{{ $personType }}', '{{ $person->id }}', '{{ $person->phone }}')" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">{{ __('messages.channel') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <x-radio-pill name="channel" value="sms" label="SMS" tone="secondary" checked/>
                    <x-radio-pill name="channel" value="whatsapp" label="WhatsApp" tone="secondary"/>
                </div>
            </div>
            <div>
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <x-radio-pill name="currency" value="AFN" :label="__('messages.afn')" tone="secondary" checked/>
                    <x-radio-pill name="currency" value="USD" :label="__('messages.usd_with_paren').'$)'" tone="secondary"/>
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

    {{-- Balances --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.1s;">
        @foreach ([
            ['label' => __('messages.afn'), 'suffix' => __('messages.afn'), 'total' => 'total_amount_afn', 'paid' => 'paid_afn', 'returned' => 'returned_afn', 'rem' => $remainingAFN, 'credit' => $creditAFN],
            ['label' => __('messages.usd_with_paren').'$)', 'suffix' => '$', 'total' => 'total_amount_usd', 'paid' => 'paid_usd', 'returned' => 'returned_usd', 'rem' => $remainingUSD, 'credit' => $creditUSD],
        ] as $c)
            <div class="card !p-3 relative overflow-hidden">
                <div class="text-[10px] font-bold text-ink-400 dark:text-ink-500 uppercase tracking-wider mb-2">{{ $c['label'] }}</div>
                <div class="space-y-1.5 text-xs tabular-nums">
                    <div class="flex justify-between">
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total') }}</span>
                        <span class="font-semibold text-ink-800 dark:text-ink-200" dir="ltr">{{ number_format((float) $summary[$c['total']], 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-primary-600 dark:text-primary-400">{{ __('messages.paid') }}</span>
                        <span class="font-semibold text-primary-600 dark:text-primary-400" dir="ltr">{{ number_format((float) $summary[$c['paid']], 0) }}</span>
                    </div>
                    @if ((float) $summary[$c['returned']] > 0)
                        <div class="flex justify-between">
                            <span class="text-secondary-600 dark:text-secondary-400">{{ __('messages.returned') }}</span>
                            <span class="font-semibold text-secondary-600 dark:text-secondary-400" dir="ltr">{{ number_format((float) $summary[$c['returned']], 0) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-1.5">
                        <span class="font-bold text-ink-700 dark:text-ink-300">{{ __('messages.pending') }}</span>
                        <span class="font-extrabold {{ $c['rem'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}" dir="ltr">{{ number_format($c['rem'], 0) }}</span>
                    </div>
                    @if ($c['credit'] > 0)
                        <div class="flex justify-between">
                            <span class="font-bold text-primary-600 dark:text-primary-400">{{ __('messages.credit') }}</span>
                            <span class="font-extrabold text-primary-600 dark:text-primary-400" dir="ltr">{{ number_format($c['credit'], 0) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Payment history --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-primary-600"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.payment_history') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @forelse ($entries as $entry)
                @php
                    $inflow = $entry->type === 'payment_received';
                    $isReturn = ($entry->kind ?? 'payment') === 'return';
                @endphp
                <div class="flex items-center justify-between gap-3 px-4 py-3 tabular-nums">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl {{ $isReturn ? 'bg-secondary-50 dark:bg-secondary-900/25 text-secondary-600 dark:text-secondary-400' : ($inflow ? 'bg-primary-50 dark:bg-primary-900/25 text-primary-600 dark:text-primary-400' : 'bg-danger-50 dark:bg-danger-900/25 text-danger-500 dark:text-danger-400') }} flex items-center justify-center flex-shrink-0">
                            <x-icon name="{{ $isReturn ? 'arrow-uturn-left' : ($inflow ? 'arrow-down-tray' : 'arrow-up-tray') }}" class="w-4 h-4" strokeWidth="1.8"/>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-ink-700 dark:text-ink-300">{{ $entry->created_at->format('Y/m/d') }} <span class="text-ink-400 dark:text-ink-500 font-medium">{{ $entry->created_at->format('H:i') }}</span></p>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate mt-0.5">
                                {{ $entry->label ?? '' }}
                                @if ($entry->notes) &middot; {{ $entry->notes }} @endif
                            </p>
                        </div>
                    </div>
                    <span class="text-sm font-extrabold flex-shrink-0 {{ $inflow ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}" dir="ltr">{{ $inflow ? '+' : '-' }}{{ number_format((float) $entry->amount, 0) }} {{ $entry->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                </div>
            @empty
                <div class="empty-state">
                    <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_payments_recorded') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- New payment --}}
    @if ($hasBalance)
    <div class="card p-4 page-enter" style="animation-delay: 0.2s;">
        <a href="{{ route('payments.create', ['party_type' => $personType, 'party_id' => $person->id]) }}" class="btn-primary w-full">
            <x-icon name="plus" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.add_payment') }}
        </a>
    </div>
    @endif
@endsection

@push('scripts')
<script>
    const ledgerReminderUrls = {
        customer: '{{ route('reminders.customer', $person) }}',
        supplier: '{{ route('reminders.supplier', $person) }}',
    };

    function submitLedgerReminder(event, type, id, phone) {
        event.preventDefault();
        const form = event.target;
        const channel = form.querySelector('input[name="channel"]:checked').value;
        const currency = form.querySelector('input[name="currency"]:checked').value;
        const amount = form.querySelector('input[name="amount"]').value;

        if (channel === 'sms') {
            const url = ledgerReminderUrls[type];
            fetch(url, {
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
            .catch(() => { form.submit(); });
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
