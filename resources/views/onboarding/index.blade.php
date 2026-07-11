<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MGS - {{ __('messages.welcome') }}</title>
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-10" id="onboarding-app">
        <!-- Step Indicators -->
        <div class="flex gap-2 mb-8" id="step-indicators">
            <div class="step-dot active" data-step="1"></div>
            <div class="step-dot" data-step="2"></div>
            <div class="step-dot" data-step="3"></div>
        </div>

        <!-- Step 1: Company Info -->
        <div class="onboarding-step active" id="step-1">
            <div class="w-16 h-16 rounded-2xl bg-brand text-white flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-500/30">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-center text-gray-900 mb-2">{{ __('messages.welcome_to_mgs') }}</h2>
            <p class="text-sm text-gray-500 text-center mb-6">{{ __('messages.setup_business_info') }}</p>

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
                        <option value="AFN">{{ __('messages.afghani_afn') }}</option>
                        <option value="USD">{{ __('messages.usd_with_paren_2') }}USD)</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full">{{ __('messages.next') }}</button>
            </form>
        </div>

        <!-- Step 2: Categories -->
        <div class="onboarding-step" id="step-2">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-secondary-500 to-secondary-700 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-secondary-500/30">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-center text-gray-900 mb-2">{{ __('messages.add_categories') }}</h2>
            <p class="text-sm text-gray-500 text-center mb-6">{{ __('messages.categories_help_organize') }}</p>

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
        <div class="onboarding-step" id="step-3">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-500/30">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-center text-gray-900 mb-2">{{ __('messages.setup_units') }}</h2>
            <p class="text-sm text-gray-500 text-center mb-6">{{ __('messages.units_help_measure') }}</p>

            <form id="step3-form" class="space-y-4 max-w-sm mx-auto">
                <div>
                    <label class="form-label">{{ __('messages.units') }} ({{ __('messages.comma_separated') }})</label>
                    <textarea name="units" rows="3" class="form-input"
                              placeholder="{{ __('messages.units_placeholder') }}"></textarea>
                    <p class="text-[10px] text-gray-400 mt-1">{{ __('messages.units_format_hint') }}</p>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="skipStep()" class="btn-secondary flex-1">{{ __('messages.skip') }}</button>
                    <button type="submit" class="btn-primary flex-1">{{ __('messages.finish') }}</button>
                </div>
            </form>
        </div>

        <!-- Success -->
        <div class="onboarding-step" id="step-success" style="display:none;">
            <div class="text-center">
                <div class="w-20 h-20 rounded-full bg-primary-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('messages.all_set') }}</h2>
                <p class="text-sm text-gray-500 mb-6">{{ __('messages.onboarding_complete_msg') }}</p>
                <a href="{{ route('dashboard') }}" class="btn-primary inline-flex">{{ __('messages.go_to_dashboard') }}</a>
            </div>
        </div>
    </div>

    <style>
        .step-dot { width: 8px; height: 8px; border-radius: 50%; background: #d1d5db; transition: all 0.3s; }
        .step-dot.active { background: #10b981; width: 24px; border-radius: 4px; }
        .step-dot.done { background: #10b981; }
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
