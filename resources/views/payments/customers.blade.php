@extends('layouts.app')

@section('content')
    <div class="page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">{{ __('messages.customer_payments') }}</h2>
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.customer_list') }}</h3>
            </div>
        </div>
        <div id="customer-list-container" class="p-4">
            <div class="text-sm text-ink-500 dark:text-ink-400 text-center py-4">{{ __('messages.loading_info') }}</div>
        </div>
    </div>

    <div id="payment-history-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="card w-full max-w-lg max-h-[80vh] flex flex-col">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 flex items-center justify-between">
                <span id="modal-customer-name" class="text-sm font-semibold text-ink-800 dark:text-ink-200"></span>
                <button id="close-modal" class="text-ink-400 hover:text-ink-600 dark:hover:text-ink-200">
                    <x-icon name="x-mark" class="w-5 h-5" strokeWidth="2"/>
                </button>
            </div>
            <div id="payment-history-content" class="p-4 overflow-y-auto flex-1">
                <div class="text-sm text-ink-500 dark:text-ink-400 text-center py-4">{{ __('messages.loading_payment_history') }}</div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const container = document.getElementById('customer-list-container');
        const modal = document.getElementById('payment-history-modal');
        const modalName = document.getElementById('modal-customer-name');
        const modalContent = document.getElementById('payment-history-content');
        const closeBtn = document.getElementById('close-modal');

        function extractUniqueCustomers(payments) {
            const customers = [], seen = new Set();
            payments.forEach(p => { if (p.customer_name && !seen.has(p.customer_name)) { seen.add(p.customer_name); customers.push({name: p.customer_name, payment_count: 0, total_spent: 0}); } });
            payments.forEach(p => { const c = customers.find(c => c.name === p.customer_name); if (c) { c.payment_count++; c.total_spent += Math.round(parseFloat(p.amount) * 100) / 100; } });
            return customers;
        }

        function renderCustomerList(customers) {
            if (customers.length === 0) { container.innerHTML = '<div class="text-sm text-ink-500 dark:text-ink-400 text-center py-4">{{ __("messages.no_customer_payments") }}</div>'; return; }
            let html = '<ul class="divide-y divide-ink-100 dark:divide-ink-700/30">';
            customers.forEach(c => {
                html += `<li class="py-3 flex items-center justify-between">
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200">
                        <button class="customer-name-btn text-primary-600 dark:text-primary-400 hover:underline focus:outline-none text-left" data-customer-name="${c.name}">${c.name}</button>
                        <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">${c.payment_count} {{ __("messages.payment") }}</div>
                    </div>
                    <span class="text-sm font-bold text-primary-600 dark:text-primary-400">${c.total_spent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} {{ __("messages.afn") }}</span>
                </li>`;
            });
            html += '</ul>';
            container.innerHTML = html;
            document.querySelectorAll('.customer-name-btn').forEach(btn => btn.addEventListener('click', function() { showPaymentHistory(this.getAttribute('data-customer-name')); }));
        }

        function showPaymentHistory(customerName) {
            fetch('/payments-data').then(r => r.json()).then(data => {
                const payments = data.payments.filter(p => p.customer_name === customerName);
                modalName.textContent = customerName;
                let html = '';
                if (payments.length === 0) html = '<div class="text-sm text-ink-500 dark:text-ink-400 text-center py-4">{{ __("messages.payment_info_not_found") }}</div>';
                else {
                    let total = 0;
                    html = '<div class="space-y-3">';
                    payments.forEach(p => { total += Math.round(parseFloat(p.amount) * 100) / 100;
                        html += `<div class="px-3 py-2.5 rounded-xl border border-ink-100 dark:border-ink-700/30">
                            <div class="flex items-center justify-between mb-1"><span class="text-sm font-medium">{{ __("messages.order") }} #${p.order_id}</span>
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">${parseFloat(p.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} {{ __("messages.afn") }}</span></div>
                            <div class="text-xs text-ink-500">${p.created_at}${p.notes ? ' | ' + p.notes : ''}</div>
                        </div>`;
                    });
                    html += `<div class="pt-3 border-t border-ink-100 dark:border-ink-700/30 flex justify-between text-sm"><span class="font-semibold">{{ __("messages.total") }}:</span><span class="font-bold text-primary-600 dark:text-primary-400">${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} {{ __("messages.afn") }}</span></div></div>`;
                }
                modalContent.innerHTML = html;
                modal.classList.remove('hidden'); modal.classList.add('flex');
            }).catch(() => { modalContent.innerHTML = '<div class="text-sm text-danger-600 text-center py-4">{{ __("messages.error_loading_data") }}</div>'; modal.classList.remove('hidden'); modal.classList.add('flex'); });
        }

        function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
        document.addEventListener('DOMContentLoaded', function() {
            fetch('/payments-data').then(r => r.json()).then(data => { renderCustomerList(extractUniqueCustomers(data.payments)); }).catch(() => { container.innerHTML = '<div class="text-sm text-danger-600 text-center py-4">{{ __("messages.error_loading_customers") }}</div>'; });
        });
    </script>
    @endpush
@endsection