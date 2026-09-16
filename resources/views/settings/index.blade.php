@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="cog-6-tooth" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.settings') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.company_information') }} &middot; {{ __('messages.invoice_settings') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Quick links: related pages --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            <a href="{{ route('app-lock.index') }}" class="list-row">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-secondary-50 dark:bg-secondary-900/30 flex items-center justify-center flex-shrink-0">
                        <x-icon name="lock-closed" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink-800 dark:text-ink-200 truncate">{{ __('messages.security_settings') }}</p>
                        <p class="text-[10px] text-ink-400 dark:text-ink-500 truncate">{{ __('messages.pin_lock') }}</p>
                    </div>
                </div>
                <x-icon name="chevron-right" class="w-4 h-4 text-ink-300 dark:text-ink-600 flex-shrink-0 rtl:-scale-x-100" strokeWidth="2"/>
            </a>
            <a href="{{ route('settings.openwa') }}" class="list-row">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                        <x-icon name="chat-bubble-left-right" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink-800 dark:text-ink-200 truncate">{{ __('messages.whatsapp_gateway') }}</p>
                        <p class="text-[10px] text-ink-400 dark:text-ink-500 truncate">WhatsApp</p>
                    </div>
                </div>
                <x-icon name="chevron-right" class="w-4 h-4 text-ink-300 dark:text-ink-600 flex-shrink-0 rtl:-scale-x-100" strokeWidth="2"/>
            </a>
        </div>
    </div>

    <form action="{{ route('settings.update') }}" method="POST">
        @csrf

        {{-- Company --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="building-storefront" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.company_information') }}</h3>
                </div>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="form-label">{{ __('messages.company_name') }}</label>
                    <div class="relative">
                        <input type="text" name="company_name" value="{{ old('company_name', $settings['company_name']) }}" class="form-input ps-10">
                        <x-icon name="building-storefront" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                </div>
                <div>
                    <label class="form-label">{{ __('messages.company_address') }}</label>
                    <textarea name="company_address" rows="2" class="form-input">{{ old('company_address', $settings['company_address']) }}</textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('messages.company_phone') }}</label>
                        <div class="relative" dir="ltr">
                            <input type="tel" name="company_phone" value="{{ old('company_phone', $settings['company_phone']) }}" class="form-input ps-10" dir="ltr">
                            <x-icon name="phone" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.company_email') }}</label>
                        <div class="relative" dir="ltr">
                            <input type="email" name="company_email" value="{{ old('company_email', $settings['company_email']) }}" class="form-input ps-10" dir="ltr">
                            <x-icon name="envelope" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Invoice settings --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="document-text" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.invoice_settings') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('messages.invoice_prefix') }}</label>
                        <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" class="form-input text-center font-bold tabular-nums" dir="ltr">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.purchase_prefix') }}</label>
                        <input type="text" name="purchase_prefix" value="{{ old('purchase_prefix', $settings['purchase_prefix']) }}" class="form-input text-center font-bold tabular-nums" dir="ltr">
                    </div>
                </div>
            </div>
        </div>

        {{-- Rate & stock --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="banknotes" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.rate_and_stock') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('messages.exchange_rate') }}</label>
                        <div class="relative" dir="ltr">
                            <input type="number" name="usd_to_afn_rate" value="{{ old('usd_to_afn_rate', $settings['usd_to_afn_rate']) }}" step="0.01" min="0.01" class="form-input ps-10 tabular-nums" dir="ltr">
                            <x-icon name="arrows-up-down" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                        </div>
                        <p class="text-[10px] text-ink-400 dark:text-ink-500 mt-1">1$ = <span id="rate-preview" class="font-bold tabular-nums">{{ number_format((float) $settings['usd_to_afn_rate']) }}</span> {{ __('messages.afn') }}</p>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.low_stock_threshold') }}</label>
                        <div class="relative" dir="ltr">
                            <input type="number" name="min_stock_threshold" value="{{ old('min_stock_threshold', $settings['min_stock_threshold']) }}" step="1" min="0" class="form-input ps-10 tabular-nums" dir="ltr">
                            <x-icon name="archive-box" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                        </div>
                        <p class="text-[10px] text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.low_stock_hint') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Language (applied instantly; buttons are type="button" so nothing submits with the form) --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.25s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="language" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.language') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-3 gap-3">
                    @foreach (['ps' => __('messages.pashto'), 'fa' => __('messages.persian'), 'en' => __('messages.english')] as $code => $label)
                        <button type="button" onclick="changeLanguage('{{ $code }}')"
                                class="px-3 py-3 rounded-xl border text-sm font-bold transition-all duration-200 active:scale-95 inline-flex items-center justify-center gap-1.5 {{ app()->getLocale() === $code ? 'border-brand bg-brand/10 dark:bg-brand/20 text-brand shadow-sm' : 'border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 hover:border-ink-300 dark:hover:border-white/[0.15]' }}">
                            @if (app()->getLocale() === $code)
                                <x-icon name="check-circle" class="w-3.5 h-3.5 flex-shrink-0" strokeWidth="2"/>
                            @endif
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <p class="text-[10px] text-ink-400 dark:text-ink-500 mt-3">{{ __('messages.language_note') }}</p>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.3s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.save') }}
        </button>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const input = document.querySelector('input[name="usd_to_afn_rate"]');
        const preview = document.getElementById('rate-preview');
        if (!input || !preview) return;
        input.addEventListener('input', () => {
            const v = parseFloat(input.value);
            preview.textContent = isFinite(v) && v > 0 ? new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(v) : '0';
        });
    })();
</script>
@endpush
