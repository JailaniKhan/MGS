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
                    <div>
                        <label class="form-label">{{ __('messages.tax_id') }}</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id', $settings['tax_id']) }}" class="form-input">
                    </div>
                </div>
            </div>

            <!-- Tax Settings -->
            <div class="mb-6 border-t border-ink-100 dark:border-ink-700/30 pt-5">
                <h4 class="section-title mb-4">{{ __('messages.tax_settings') }}</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('messages.default_tax_rate') }}</label>
                        <input type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', $settings['default_tax_rate']) }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.default_tax_type') }}</label>
                        <select name="default_tax_type" class="form-input">
                            <option value="exclusive" {{ $settings['default_tax_type'] === 'exclusive' ? 'selected' : '' }}>{{ __('messages.exclusive') }} (Exclusive)</option>
                            <option value="inclusive" {{ $settings['default_tax_type'] === 'inclusive' ? 'selected' : '' }}>{{ __('messages.inclusive') }} (Inclusive)</option>
                        </select>
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

            <!-- Reminder / AI Settings -->
            <div class="mb-6 border-t border-ink-100 dark:border-ink-700/30 pt-5">
                <h4 class="section-title mb-4">{{ __('messages.reminder_settings') }}</h4>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">{{ __('messages.whatsapp_api_key') }}</label>
                        <input type="password" name="whatsapp_api_key" value="{{ old('whatsapp_api_key', $settings['whatsapp_api_key']) }}" class="form-input" placeholder="Meta WhatsApp API Token">
                        <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.whatsapp_api_key_info') }}</p>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.whatsapp_phone_number_id') }}</label>
                        <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $settings['whatsapp_phone_number_id']) }}" class="form-input" placeholder="WhatsApp Phone Number ID">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.anthropic_api_key') }}</label>
                        <input type="password" name="anthropic_api_key" value="{{ old('anthropic_api_key', $settings['anthropic_api_key']) }}" class="form-input" placeholder="Claude API Key">
                        <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.anthropic_api_key_info') }}</p>
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

            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('messages.save') }}</button>
        </form>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('settings.openwa') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400 mb-3"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.5h9a3 3 0 013 3v9a3 3 0 01-3 3h-9a3 3 0 01-3-3v-9a3 3 0 013-3z"/></svg>{{ __('messages.whatsapp_gateway') }}</a>
        <br>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}</a>
    </div>
</div>
@endsection