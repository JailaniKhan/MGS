@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.people') }}</h2>
    </div>

    <div class="flex gap-1.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <button id="tab-customers" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-primary-500 text-white shadow-sm shadow-primary-500/20" onclick="switchTab('customers')">
            {{ __('messages.customers') }}
        </button>
        <button id="tab-suppliers" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 border border-ink-200 dark:border-ink-700" onclick="switchTab('suppliers')">
            {{ __('messages.suppliers') }}
        </button>
    </div>

    <div id="section-customers" class="tab-section page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.customers') }}</span>
            <a href="{{ route('customers.create') }}" class="btn-primary btn-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                {{ __('messages.new_customer') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($customers as $customer)
                    <div class="swipe-row">
                        <a href="{{ route('customers.show', $customer) }}" class="swipe-content">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-secondary-500 to-secondary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                <span class="text-white font-bold text-sm">{{ substr($customer->name, 0, 1) }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $customer->name }}</div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    @if ($customer->phone)<span class="text-[11px] text-ink-500 dark:text-ink-400">{{ $customer->phone }}</span>@endif
                                    @if ($customer->orders_count)<span class="badge-info text-[10px] px-1.5 py-0.5">{{ $customer->orders_count }} {{ __('messages.orders') }}</span>@endif
                                </div>
                            </div>
                        </a>
                        <div class="swipe-actions">
                            <a href="{{ route('reminders.customer', $customer) }}" onclick="event.preventDefault(); sendReminder('{{ $customer->id }}', '{{ $customer->name }}', 'customer')" class="act-edit" title="{{ __('messages.send_reminder') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                            </a>
                            <a href="{{ route('customers.edit', $customer) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                </svg>
                            </a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_customers') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div id="section-suppliers" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.suppliers') }}</span>
            <a href="{{ route('suppliers.create') }}" class="btn-primary btn-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                {{ __('messages.new_supplier') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($suppliers as $supplier)
                    <div class="swipe-row">
                        <div class="swipe-content">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                <span class="text-white font-bold text-sm">{{ substr($supplier->name, 0, 1) }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $supplier->name }}</div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    @if ($supplier->phone)<span class="text-[11px] text-ink-500 dark:text-ink-400">{{ $supplier->phone }}</span>@endif
                                    @if ($supplier->purchases_count)<span class="badge-info text-[10px] px-1.5 py-0.5">{{ $supplier->purchases_count }} {{ __('messages.purchases') }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="swipe-actions">
                            <a href="{{ route('reminders.supplier', $supplier) }}" onclick="event.preventDefault(); sendReminder('{{ $supplier->id }}', '{{ $supplier->name }}', 'supplier')" class="act-edit" title="{{ __('messages.send_reminder') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                            </a>
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                </svg>
                            </a>
                            <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_suppliers') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Reminder Channel Modal -->
    <div id="reminder-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
        <div class="card w-[88%] max-w-sm p-5 page-enter">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.reminder_confirm_title') }}</h3>
            </div>
            <p class="text-sm text-ink-600 dark:text-ink-300 mb-4">{{ __('messages.reminder_confirm_body') }} <span id="reminder-modal-name" class="font-bold"></span></p>
            <div class="flex gap-2">
                <button type="button" class="btn-primary flex-1" onclick="submitReminder('sms')">{{ __('messages.channel') }}: SMS</button>
                <button type="button" class="btn-primary flex-1" onclick="submitReminder('whatsapp')">WhatsApp</button>
            </div>
            <button type="button" class="btn-secondary w-full mt-3" onclick="closeReminderModal()">{{ __('messages.cancel') }}</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    let reminderTarget = { id: null, type: null, name: '' };

    function sendReminder(id, name, type) {
        reminderTarget = { id, type, name };
        document.getElementById('reminder-modal-name').textContent = name;
        document.getElementById('reminder-modal').classList.remove('hidden');
    }

    function closeReminderModal() {
        document.getElementById('reminder-modal').classList.add('hidden');
    }

    function submitReminder(channel) {
        const url = reminderTarget.type === 'customer'
            ? '{{ url('reminders/customer') }}/' + reminderTarget.id
            : '{{ url('reminders/supplier') }}/' + reminderTarget.id;

        if (channel === 'sms') {
            // Send via backend to generate the message, then open native SMS app
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ channel: 'sms' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                const phone = data.phone.replace(/[^0-9]/g, '');
                const msg = encodeURIComponent(data.message);
                openNativeSms(phone, msg);
                closeReminderModal();
            })
            .catch(() => {
                // Fallback: if fetch fails (non-JSON response), treat as form submit
                fallbackFormSubmit(url);
            });
        } else {
            fallbackFormSubmit(url, channel);
        }
    }

    function fallbackFormSubmit(url, channel) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        if (channel) {
            const ch = document.createElement('input');
            ch.type = 'hidden';
            ch.name = 'channel';
            ch.value = channel;
            form.appendChild(ch);
        }
        document.body.appendChild(form);
        form.submit();
    }
    function openNativeSms(phone, encodedMsg) {
        const smsUrl = 'sms:' + phone + '?body=' + encodedMsg;
        const before = window.location.href;
        window.location.href = smsUrl;
        // On desktop (no SMS app) the navigation is a no-op; show the message so it's verifiable
        setTimeout(() => {
            if (window.location.href === before) {
                prompt('SMS not supported in this browser. Copy the message below to send it manually:', decodeURIComponent(encodedMsg));
            }
        }, 600);
    }

    function switchTab(tab) {
        document.querySelectorAll('.tab-section').forEach(el => el.classList.add('hidden'));
        document.getElementById('section-' + tab).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('bg-primary-500', 'text-white', 'shadow-sm', 'shadow-primary-500/20');
            el.classList.add('bg-ink-100', 'dark:bg-ink-800', 'text-ink-600', 'dark:text-ink-300', 'border', 'border-ink-200', 'dark:border-ink-700');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.remove('bg-ink-100', 'dark:bg-ink-800', 'text-ink-600', 'dark:text-ink-300', 'border', 'border-ink-200', 'dark:border-ink-700');
        activeBtn.classList.add('bg-primary-500', 'text-white', 'shadow-sm', 'shadow-primary-500/20');
    }
</script>
@endpush