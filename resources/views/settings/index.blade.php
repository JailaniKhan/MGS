@extends('layouts.app')

@section('content')
<div class="page-enter">
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">{{ __('messages.settings') }}</h2>

    <div class="card p-4">
        <form action="{{ route('settings.update') }}" method="POST">
            @csrf

            <!-- Company Info -->
            <div class="mb-6">
                <h4 class="section-title mb-4">{{ __('messages.company_information') }}</h4>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">{{ __('messages.company_name') }}</label>
                        <input type="text" name="company_name" value="{{ old('company_name', $settings['company_name']) }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.company_address') }}</label>
                        <textarea name="company_address" rows="2" class="form-input">{{ old('company_address', $settings['company_address']) }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('messages.company_phone') }}</label>
                            <input type="text" name="company_phone" value="{{ old('company_phone', $settings['company_phone']) }}" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">{{ __('messages.company_email') }}</label>
                            <input type="email" name="company_email" value="{{ old('company_email', $settings['company_email']) }}" class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Settings -->
            <div class="mb-6 border-t border-ink-100 dark:border-ink-700/30 pt-5">
                <h4 class="section-title mb-4">{{ __('messages.invoice_settings') }}</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('messages.invoice_prefix') }}</label>
                        <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.currency') }}</label>
                        <select name="currency" class="form-input">
                            <option value="AFN" {{ $settings['currency'] === 'AFN' ? 'selected' : '' }}>{{ __('messages.afghani_with_paren2') }}AFN)</option>
                            <option value="USD" {{ $settings['currency'] === 'USD' ? 'selected' : '' }}>{{ __('messages.usd_with_paren_2') }}USD)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Language -->
            <div class="mb-6 border-t border-ink-100 dark:border-ink-700/30 pt-5">
                <h4 class="section-title mb-4">{{ __('messages.language') }}</h4>
                <div>
                    <label class="form-label">{{ __('messages.select_language') }}</label>
                    <select name="language" class="form-input">
                        <option value="ps" {{ $settings['language'] === 'ps' || $settings['language'] === null ? 'selected' : '' }}>{{ __('messages.pashto') }}</option>
                        <option value="fa" {{ $settings['language'] === 'fa' ? 'selected' : '' }}>{{ __('messages.persian') }}</option>
                        <option value="en" {{ $settings['language'] === 'en' ? 'selected' : '' }}>{{ __('messages.english') }}</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.save') }}</button>
        </form>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400"><x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}</a>
    </div>
</div>
@endsection