@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm flex-shrink-0">
                <x-icon name="user" class="w-5 h-5 text-white"/>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ $person->name }}</h2>
                    <span class="badge {{ $personType === 'customer' ? 'badge-info' : 'badge-warning' }}">{{ $personLabel }}</span>
                </div>
                @if ($person->phone) <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">{{ __('messages.phone') }}: {{ $person->phone }}</p> @endif
                @if ($person->address) <p class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.address') }}: {{ $person->address }}</p> @endif
            </div>
            <a href="{{ $personType === 'customer' ? route('reminders.customer', $person) : route('reminders.supplier', $person) }}"
               onclick="event.preventDefault(); document.getElementById('reminder-form-ledger').classList.toggle('hidden')"
               class="w-10 h-10 flex items-center justify-center text-secondary-600 dark:text-secondary-400 hover:bg-secondary-50 dark:hover:bg-secondary-900/20 rounded-xl transition-all duration-200">
                <x-icon name="bell" class="w-5 h-5"/>
            </a>
        </div>
    </div>

    <!-- Send Reminder Form (toggled) -->
    <div id="reminder-form-ledger" class="card p-4 mb-4 hidden page-enter" style="animation-delay: 0.08s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.send_reminder') }}</h3>
        </div>
        <form onsubmit="return submitLedgerReminder(event, '{{ $personType }}', '{{ $person->id }}', '{{ $person->phone }}')" class="space-y-3">
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
                <x-icon name="bell" class="w-4 h-4"/>{{ __('messages.send_reminder') }}
            </button>
        </form>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="metric-tile !p-4">
            <div class="text-xs font-medium text-ink-500 dark:text-ink-400 uppercase tracking-wider mb-3">{{ __('messages.afn') }}</div>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-ink-500 dark:text-ink-400">{{ __('messages.total') }}:</span><span class="font-medium">{{ number_format($totalAFN) }} {{ __('messages.afn') }}</span></div>
                <div class="flex justify-between"><span class="text-primary-600 dark:text-primary-400">{{ __('messages.paid') }}:</span><span class="font-medium text-primary-600 dark:text-primary-400">{{ number_format($paidAFN) }} {{ __('messages.afn') }}</span></div>
                <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-1.5"><span class="font-semibold text-ink-700 dark:text-ink-300">{{ __('messages.pending') }}:</span><span class="font-bold {{ $totalAFN - $paidAFN > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}">{{ number_format(max(0, $totalAFN - $paidAFN)) }} {{ __('messages.afn') }}</span></div>
            </div>
        </div>
        <div class="metric-tile !p-4">
            <div class="text-xs font-medium text-ink-500 dark:text-ink-400 uppercase tracking-wider mb-3">{{ __('messages.usd_with_paren') }}$)</div>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-ink-500 dark:text-ink-400">{{ __('messages.total') }}:</span><span class="font-medium">{{ number_format($totalUSD) }}$</span></div>
                <div class="flex justify-between"><span class="text-primary-600 dark:text-primary-400">{{ __('messages.paid') }}:</span><span class="font-medium text-primary-600 dark:text-primary-400">{{ number_format($paidUSD) }}$</span></div>
                <div class="flex justify-between border-t border-ink-100 dark:border-ink-700/30 pt-1.5"><span class="font-semibold text-ink-700 dark:text-ink-300">{{ __('messages.pending') }}:</span><span class="font-bold {{ $totalUSD - $paidUSD > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400' }}">{{ number_format(max(0, $totalUSD - $paidUSD)) }}$</span></div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.payment_history') }}</h3>
            </div>
        </div>
        @forelse ($entries as $entry)
            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200">{{ $entry->created_at->format('Y/m/d H:i') }}</div>
                    @if ($entry->notes) <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">{{ $entry->notes }}</div> @endif
                </div>
                <div class="text-end">
                    <span class="text-sm font-bold {{ $entry->type === 'payment_received' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">{{ $entry->type === 'payment_received' ? '+' : '-' }}{{ number_format($entry->amount) }} {{ $entry->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                </div>
            </div>
        @empty
            <div class="empty-state"><p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_payments_recorded') }}</p></div>
        @endforelse
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.2s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.new_payment') }}</h3>
        </div>
        <form action="{{ route('ledger.payment.store', [$personType, $person->id]) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="AFN" checked class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="USD" class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.usd_with_paren') }}$)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.payment_amount') }}</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="form-input">
                @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.notes_optional') }}</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="form-input">
                @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="plus" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.record_payment') }}</button>
        </form>
    </div>
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
                if (data.error) { alert(data.error); return; }
                const p = data.phone.replace(/[^0-9]/g, '');
                const msg = encodeURIComponent(data.message);
                openNativeSms(p, msg);
            })
            .catch(() => { form.submit(); });
        } else {
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
