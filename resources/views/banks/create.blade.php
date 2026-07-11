@extends('layouts.app')

@section('content')
<div class="mb-4 page-enter">
    <a href="{{ route('banks.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
    </a>
    <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.new_bank_account') }}</h2>
</div>

<div class="card p-4 page-enter" style="animation-delay: 0.1s;">
    <form method="POST" action="{{ route('banks.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label class="form-label">{{ __('messages.account_name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
                @error('name') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('messages.bank_name') }}</label>
                <input type="text" name="bank_name" value="{{ old('bank_name') }}" required class="form-input">
                @error('bank_name') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('messages.account_number') }}</label>
                <input type="text" name="account_number" value="{{ old('account_number') }}" class="form-input">
                @error('account_number') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <select name="currency" class="form-select">
                    <option value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>$</option>
                </select>
                @error('currency') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('messages.register') }}</button>
        </div>
    </form>
</div>
@endsection