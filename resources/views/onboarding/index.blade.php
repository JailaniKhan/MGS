<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ps', 'fa']) ? 'rtl' : 'ltr' }}" data-language-url="{{ route('language.update') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MGS - {{ __('messages.welcome') }}</title>
    <script>
        try {
            const t = localStorage.getItem('mgs-theme');
            const d = t === 'dark' || ((!t || t === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (d) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @fonts
    @if(in_array(app()->getLocale(), ['ps', 'fa']))
        @vite(['resources/css/app-rtl.css', 'resources/js/app.js'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] min-h-screen text-ink-700 dark:text-ink-200 font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-10" id="onboarding-app">
        <!-- Language switch -->
        <div class="w-full max-w-sm flex justify-end mb-4">
            <select onchange="changeLanguage(this.value)"
                class="text-[11px] bg-white/80 dark:bg-[#16181c] border border-ink-200 dark:border-ink-700 rounded-xl px-2.5 py-2
                       appearance-none cursor-pointer transition-all duration-200 hover:border-primary-300 dark:hover:border-primary-600
                       focus:outline-none focus:ring-2 focus:ring-primary-500/30 font-semibold text-ink-700 dark:text-ink-300">
                <option value="ps" {{ app()->getLocale() === 'ps' ? 'selected' : '' }}>{{ __('messages.pashto') }}</option>
                <option value="fa" {{ app()->getLocale() === 'fa' ? 'selected' : '' }}>{{ __('messages.persian') }}</option>
                <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>{{ __('messages.english') }}</option>
            </select>
        </div>

        <!-- Step Indicators -->
        <div class="flex gap-2 mb-8" id="step-indicators">
            <div class="step-dot active" data-step="1"></div>
            <div class="step-dot" data-step="2"></div>
            <div class="step-dot" data-step="3"></div>
        </div>

        <!-- Step 1: Company Info -->
        <div class="onboarding-step active w-full" id="step-1">
            <div class="w-16 h-16 rounded-[1.25rem] brand-grad flex items-center justify-center mx-auto mb-4 shadow-fab">
                <x-icon name="building-office" class="w-8 h-8 text-white"/>
            </div>
            <h2 class="text-xl font-bold text-center text-ink-900 dark:text-white mb-2">{{ __('messages.welcome_to_mgs') }}</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 text-center mb-6">{{ __('messages.setup_business_info') }}</p>

            <form id="step1-form" class="space-y-4 max-w-sm mx-auto">
                <div>
                    <label class="form-label">{{ __('messages.company_name') }} *</label>
                    <input type="text" name="company_name" class="form-input" placeholder="{{ __('messages.company_name') }}" required>
                </div>
                <div>
                    <label class="form-label">{{ __('messages.company_phone') }}</label>
                    <input type="text" name="company_phone" class="form-input" placeholder="{{ __('messages.company_phone') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('messages.company_address') }}</label>
                    <textarea name="company_address" rows="2" class="form-input" placeholder="{{ __('messages.company_address') }}"></textarea>
                </div>
                <div>
                    <label class="form-label">{{ __('messages.currency') }} *</label>
                    <select name="currency" class="form-input" required>
                        <option value="AFN">{{ __('messages.afghani_with_paren') }}AFN)</option>
                        <option value="USD">{{ __('messages.usd_with_paren_2') }}USD)</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full">{{ __('messages.next') }}</button>
            </form>
        </div>

        <!-- Step 2: Categories -->
        <div class="onboarding-step w-full" id="step-2">
            <div class="w-16 h-16 rounded-[1.25rem] bg-secondary-500 flex items-center justify-center mx-auto mb-4 shadow-card">
                <x-icon name="tag" class="w-8 h-8 text-white"/>
            </div>
            <h2 class="text-xl font-bold text-center text-ink-900 dark:text-white mb-2">{{ __('messages.add_categories') }}</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 text-center mb-6">{{ __('messages.categories_help_organize') }}</p>

            <form id="step2-form" class="space-y-4 max-w-sm mx-auto">
                <div>
                    <label class="form-label">{{ __('messages.categories') }} ({{ __('messages.comma_separated') }})</label>
                    <textarea name="categories" rows="3" class="form-input"
                              placeholder="{{ __('messages.categories_placeholder') }}"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="skipStep()" class="btn-secondary flex-1">{{ __('messages.skip') }}</button>
                    <button type="submit" class="btn-primary flex-1">{{ __('messages.next') }}</button>
                </div>
            </form>
        </div>

        <!-- Step 3: Units -->
        <div class="onboarding-step w-full" id="step-3">
            <div class="w-16 h-16 rounded-[1.25rem] bg-accent-500 flex items-center justify-center mx-auto mb-4 shadow-card">
                <x-icon name="squares-2x2" class="w-8 h-8 text-white"/>
            </div>
            <h2 class="text-xl font-bold text-center text-ink-900 dark:text-white mb-2">{{ __('messages.setup_units') }}</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 text-center mb-6">{{ __('messages.units_help_measure') }}</p>

            <form id="step3-form" class="space-y-4 max-w-sm mx-auto">
                <div>
                    <label class="form-label">{{ __('messages.units') }} ({{ __('messages.comma_separated') }})</label>
                    <textarea name="units" rows="3" class="form-input"
                              placeholder="{{ __('messages.units_placeholder') }}"></textarea>
                    <p class="text-[10px] text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.units_format_hint') }}</p>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="skipStep()" class="btn-secondary flex-1">{{ __('messages.skip') }}</button>
                    <button type="submit" class="btn-primary flex-1">{{ __('messages.finish') }}</button>
                </div>
            </form>
        </div>

        <!-- Success -->
        <div class="onboarding-step w-full" id="step-success" style="display:none;">
            <div class="text-center">
                <div class="w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center mx-auto mb-4">
                    <x-icon name="check" class="w-10 h-10 text-primary-600 dark:text-primary-400" strokeWidth="2"/>
                </div>
                <h2 class="text-xl font-bold text-ink-900 dark:text-white mb-2">{{ __('messages.all_set') }}</h2>
                <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.onboarding_complete_msg') }}</p>
                <a href="{{ route('dashboard') }}" class="btn-primary inline-flex">{{ __('messages.go_to_dashboard') }}</a>
            </div>
        </div>
    </div>

    <style>
        .step-dot { width: 8px; height: 8px; border-radius: 50%; background: #d1d5db; transition: all 0.3s; }
        .dark .step-dot { background: rgba(255, 255, 255, 0.15); }
        .step-dot.active { background: var(--color-brand); width: 24px; border-radius: 4px; }
        .step-dot.done { background: var(--color-brand); }
        .onboarding-step { display: none; animation: fadeUp 0.35s ease-out both; }
        .onboarding-step.active { display: block; }
        @keyframes fadeUp { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>

    <script>
        let currentStep = 1;

        function showStep(step) {
            document.querySelectorAll('.onboarding-step').forEach(s => s.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');

            document.querySelectorAll('.step-dot').forEach(d => {
                const s = parseInt(d.dataset.step);
                d.classList.remove('active', 'done');
                if (s < step) d.classList.add('done');
                if (s === step) d.classList.add('active');
            });
            currentStep = step;
        }

        function skipStep() {
            if (currentStep < 3) {
                showStep(currentStep + 1);
            }
        }

        document.getElementById('step1-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('{{ route("onboarding.step1") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData
            });
            if (response.ok) showStep(2);
        });

        document.getElementById('step2-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('{{ route("onboarding.step2") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData
            });
            if (response.ok) showStep(3);
        });

        document.getElementById('step3-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('{{ route("onboarding.step3") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData
            });
            if (response.ok) {
                document.querySelectorAll('.onboarding-step').forEach(s => s.classList.remove('active'));
                document.getElementById('step-success').style.display = 'block';
                document.querySelectorAll('.step-dot').forEach(d => d.classList.add('done'));
            }
        });
    </script>
</body>
</html>
