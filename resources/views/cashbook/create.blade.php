@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('cashbook.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.new_cashbook_entry') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form method="POST" action="{{ route('cashbook.store') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.type') }}</label>
                <select name="type" class="form-input">
                    <option value="in">{{ __('messages.income_entry') }}</option>
                    <option value="out">{{ __('messages.expense_entry') }}</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN">{{ __('messages.afn') }}</option>
                    <option value="USD">$</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.amount_amount') }}</label>
                <input type="number" step="0.01" name="amount" required class="form-input">
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.person') }}</label>
                <select id="person-select" class="form-input">
                    <option value="">{{ __('messages.none') }}</option>

                        @foreach ($customers as $customer)
                            <option value="customer:{{ $customer->id }}">{{ $customer->name }} ({{ __('messages.customer') }})</option>
                        @endforeach

                        @foreach ($suppliers as $supplier)
                            <option value="supplier:{{ $supplier->id }}">{{ $supplier->name }} ({{ __('messages.supplier') }})</option>
                        @endforeach

                </select>
                <input type="hidden" name="person_id" id="person-id">
                <input type="hidden" name="person_type" id="person-type">
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const personSelect = document.getElementById('person-select');
                    const personId = document.getElementById('person-id');
                    const personType = document.getElementById('person-type');
                    function updatePerson() {
                        const val = personSelect.value;
                        if (val) {
                            const parts = val.split(':');
                            personType.value = parts[0];
                            personId.value = parts[1];
                        } else {
                            personType.value = '';
                            personId.value = '';
                        }
                    }
                    personSelect.addEventListener('change', updatePerson);
                    updatePerson();
                });
            </script>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.date') }}</label>
                <input type="date" name="entry_date" value="{{ date('Y-m-d') }}" required class="form-input">
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.notes') }}</label>
                <input type="text" name="notes" class="form-input">
            </div>
            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>{{ __('messages.register') }}</button>
        </form>
    </div>
@endsection