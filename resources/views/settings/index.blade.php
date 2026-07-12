@extends('layouts.app')

@section('content')
<div class="page-enter">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.settings') }}</h2>

    <div class="card p-4">
        <form action="{{ route('settings.update') }}" method="POST">
            @csrf

            <!-- Company Info -->
            <div class="mb-6">
                <h4 class="section-header mb-4">{{ __('messages.company_information') }}</h4>
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
            <div class="mb-6 border-t border-gray-100 dark:border-gray-700/30 pt-5">
                <h4 class="section-header mb-4">{{ __('messages.tax_settings') }}</h4>
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
            <div class="mb-6 border-t border-gray-100 dark:border-gray-700/30 pt-5">
                <h4 class="section-header mb-4">{{ __('messages.invoice_settings') }}</h4>
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
            <div class="mb-6 border-t border-gray-100 dark:border-gray-700/30 pt-5">
                <h4 class="section-header mb-4">{{ __('messages.reminder_settings') }}</h4>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">{{ __('messages.whatsapp_api_key') }}</label>
                        <input type="password" name="whatsapp_api_key" value="{{ old('whatsapp_api_key', $settings['whatsapp_api_key']) }}" class="form-input" placeholder="Meta WhatsApp API Token">
                        <p class="text-[10px] text-gray-400 mt-1">{{ __('messages.whatsapp_api_key_info') }}</p>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.whatsapp_phone_number_id') }}</label>
                        <input type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $settings['whatsapp_phone_number_id']) }}" class="form-input" placeholder="Phone Number ID">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.anthropic_api_key') }}</label>
                        <input type="password" name="anthropic_api_key" value="{{ old('anthropic_api_key', $settings['anthropic_api_key']) }}" class="form-input" placeholder="Claude API Key">
                        <p class="text-[10px] text-gray-400 mt-1">{{ __('messages.anthropic_api_key_info') }}</p>
                    </div>
                </div>
            </div>

            <!-- Language -->
            <div class="mb-6 border-t border-gray-100 dark:border-gray-700/30 pt-5">
                <h4 class="section-header mb-4">{{ __('messages.language') }}</h4>
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

    <!-- Quick Links -->
    <div class="mt-4 mb-4">
        <h4 class="section-header mb-3">{{ __('messages.features') }}</h4>
        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route('payments.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.wallet') }}</span>
            </a>
            <a href="{{ route('staff.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.staff_book') }}</span>
            </a>
            <a href="{{ route('passbook.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.passbook') }}</span>
            </a>
            <a href="{{ route('spend-breakdown.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.spend_breakdown') }}</span>
            </a>
            <a href="{{ route('app-lock.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.app_lock') }}</span>
            </a>
            <a href="{{ route('reports.daybook') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.daybook') }}</span>
            </a>
            <a href="{{ route('reports.stock') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.stock_report') }}</span>
            </a>
            <a href="{{ route('reports.profit-loss') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.profit_loss_report') }}</span>
            </a>
            <a href="{{ route('reports.balance-sheet') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.balance_sheet') }}</span>
            </a>
            <a href="{{ route('reports.aging') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.aging_report') }}</span>
            </a>
            <a href="{{ route('ledger.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.ledger') }}</span>
            </a>
            <a href="{{ route('reminders.history') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.reminders') }}</span>
            </a>
            <a href="{{ route('backup.index') }}" class="action-card">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.backups') }}</span>
            </a>
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}</a>
    </div>
</div>
@endsection