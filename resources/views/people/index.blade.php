@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.people') }}</h2>
    </div>

    <div class="segmented mb-4 page-enter" style="animation-delay: 0.05s;">
        <button id="tab-customers" class="tab-btn segmented-item segmented-item-active" onclick="switchTab('customers')">
            {{ __('messages.customers') }}
        </button>
        <button id="tab-suppliers" class="tab-btn segmented-item" onclick="switchTab('suppliers')">
            {{ __('messages.suppliers') }}
        </button>
    </div>

    <div id="section-customers" class="tab-section page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.customers') }}</span>
            <a href="{{ route('customers.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
                {{ __('messages.new_customer') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($customers as $customer)
                    <div class="swipe-row">
                        <a href="{{ route('customers.show', $customer) }}" class="swipe-content">
                            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 flex items-center justify-center flex-shrink-0 border border-secondary-100 dark:border-secondary-800/40">
                                <span class="text-secondary-600 dark:text-secondary-300 font-bold text-sm">{{ substr($customer->name, 0, 1) }}</span>
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
                                <x-icon name="bell" class="w-4 h-4"/>
                            </a>
                            <a href="{{ route('customers.edit', $customer) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <x-icon name="pencil-square" class="w-4 h-4"/>
                            </a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <x-icon name="trash" class="w-4 h-4"/>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-illustration">
                            <x-icon name="user" class="w-6 h-6 text-ink-400"/>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_customers') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
        @if ($customers->hasPages())
            <div class="mt-3">{{ $customers->links() }}</div>
        @endif
    </div>

    <div id="section-suppliers" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.suppliers') }}</span>
            <a href="{{ route('suppliers.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
                {{ __('messages.new_supplier') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($suppliers as $supplier)
                    <div class="swipe-row">
                        <div class="swipe-content">
                            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0 border border-accent-100 dark:border-accent-800/40">
                                <span class="text-secondary-600 dark:text-secondary-300 font-bold text-sm">{{ substr($supplier->name, 0, 1) }}</span>
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
                                <x-icon name="bell" class="w-4 h-4"/>
                            </a>
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <x-icon name="pencil-square" class="w-4 h-4"/>
                            </a>
                            <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <x-icon name="trash" class="w-4 h-4"/>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-illustration">
                            <x-icon name="user" class="w-6 h-6 text-ink-400"/>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_suppliers') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
        @if ($suppliers->hasPages())
            <div class="mt-3">{{ $suppliers->links() }}</div>
        @endif
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
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('segmented-item-active'));
        document.getElementById('tab-' + tab).classList.add('segmented-item-active');
    }

    @if (request()->has('suppliers_page'))
        switchTab('suppliers');
    @endif
</script>
@endpush