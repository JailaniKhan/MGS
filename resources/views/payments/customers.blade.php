@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold">د مشتریانو پېسو historia</h2>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">د مشتریانو لیست</h3>
        </div>
        <div id="customer-list-container" class="px-4 py-3">
            <div class="text-sm text-gray-500 dark:text-gray-400">د معلوماتو په لوڅولو...</div>
        </div>
    </div>

    <div id="payment-history-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 w-full max-w-lg max-h-[80vh] flex flex-col">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <span id="modal-customer-name" class="text-sm font-semibold text-gray-700 dark:text-gray-300"></span>
                <button id="close-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="payment-history-content" class="p-4 overflow-y-auto flex-1">
                <div class="text-sm text-gray-500 dark:text-gray-400">د پېسو په تاريخ لوڅولو...</div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const customerListContainer = document.getElementById('customer-list-container');
        const modal = document.getElementById('payment-history-modal');
        const modalCustomerName = document.getElementById('modal-customer-name');
        const paymentHistoryContent = document.getElementById('payment-history-content');
        const closeModalBtn = document.getElementById('close-modal');

        function extractUniqueCustomers(payments) {
            const customers = [];
            const seen = new Set();

            payments.forEach(payment => {
                if (payment.customer_name && !seen.has(payment.customer_name)) {
                    seen.add(payment.customer_name);
                    customers.push({
                        name: payment.customer_name,
                        payment_count: 0,
                        total_spent: 0
                    });
                }
            });

            payments.forEach(payment => {
                const customer = customers.find(c => c.name === payment.customer_name);
                if (customer) {
                    customer.payment_count += 1;
                    customer.total_spent += Math.round(parseFloat(payment.amount) * 100) / 100;
                }
            });

            return customers;
        }

        function renderCustomerList(customers) {
            let html = '';

            if (customers.length === 0) {
                html = '<div class="text-sm text-gray-500 dark:text-gray-400">تر اوسه د مشتریانو پېسو شتون نلري.</div>';
                customerListContainer.innerHTML = html;
                return;
            }

            html += '<ul class="divide-y divide-gray-100 dark:divide-gray-700">';
            customers.forEach(customer => {
                html += `
                    <li class="py-3 flex items-center justify-between">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            <button class="customer-name-btn text-[#0d9488] hover:underline focus:outline-none" data-customer-name="${customer.name}">
                                ${customer.name}
                            </button>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${customer.payment_count} تادیه</div>
                        </div>
                        <span class="text-sm font-bold text-green-600 dark:text-green-400">${customer.total_spent.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} افغ</span>
                    </li>
                `;
            });
            html += '</ul>';

            customerListContainer.innerHTML = html;

            document.querySelectorAll('.customer-name-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const customerName = this.getAttribute('data-customer-name');
                    showPaymentHistory(customerName);
                });
            });
        }

        function showPaymentHistory(customerName) {
            fetch('/payments-data')
                .then(response => response.json())
                .then(data => {
                    const customerPayments = data.payments.filter(p => p.customer_name === customerName);

                    modalCustomerName.textContent = customerName;

                    let html = '';
                    if (customerPayments.length === 0) {
                        html = '<div class="text-sm text-gray-500 dark:text-gray-400">د مشتری لپاره پېسو معلومات ونه موندل شول.</div>';
                    } else {
                        html += '<div class="space-y-3">';
                        let totalSpent = 0;

                        customerPayments.forEach(payment => {
                            totalSpent += Math.round(parseFloat(payment.amount) * 100) / 100;
                            html += `
                                <div class="px-3 py-2 rounded-lg border border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">امر #${payment.order_id}</span>
                                        <span class="text-sm font-bold text-green-600 dark:text-green-400">${parseFloat(payment.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} افغ</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ${payment.created_at}
                                        ${payment.notes ? ' | ' + payment.notes : ''}
                                    </div>
                                </div>
                            `;
                        });

                        html += `
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">ټولی خرچه:</span>
                                    <span class="font-bold text-green-600 dark:text-green-400">${totalSpent.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} افغ</span>
                                </div>
                            </div>
                        `;
                        html += '</div>';
                    }

                    paymentHistoryContent.innerHTML = html;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                })
                .catch(error => {
                    console.error('Error fetching payments:', error);
                    paymentHistoryContent.innerHTML = '<div class="text-sm text-red-600">د معلوماتو په الوتلو کې ستونزه رامنځته شوه.</div>';
                });
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        closeModalBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            fetch('/payments-data')
                .then(response => response.json())
                .then(data => {
                    const customers = extractUniqueCustomers(data.payments);
                    renderCustomerList(customers);
                })
                .catch(error => {
                    console.error('Error loading customer list:', error);
                    customerListContainer.innerHTML = '<div class="text-sm text-red-600">د مشتریانو لست په لوڅولو کې ستونزه رامنځته شوه.</div>';
                });
        });
    </script>
    @endpush
@endsection
