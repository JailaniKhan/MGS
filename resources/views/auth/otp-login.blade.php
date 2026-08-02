@php
    use App\Models\Setting;
    $locale = Setting::get('language', app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['ps', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MGS - {{ __('messages.login') }}</title>
    @fonts
    @if(in_array($locale, ['ps', 'fa']))
        @vite(['resources/css/app-rtl.css', 'resources/js/app.js'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] min-h-screen flex items-center justify-center p-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-brand text-white flex items-center justify-center mx-auto shadow-lg shadow-primary-500/30 mb-3">
                <span class="text-white font-bold text-2xl">M</span>
            </div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-white">MGS</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ __('messages.business_mgmt_system') }}</p>
        </div>

        <!-- OTP Login Card -->
        <div class="bg-white dark:bg-[#16181c] rounded-2xl border border-ink-100 dark:border-white/[0.06] p-6 shadow-card">
            <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ __('messages.phone_login') }}</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.enter_phone_for_otp') }}</p>

            <div id="step-phone">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.phone') }}</label>
                    <input type="tel" id="phone-input" class="form-input" placeholder="+93 XXX XXX XXX" autocomplete="tel">
                </div>
                <button onclick="sendOtp()" id="send-otp-btn" class="btn-primary w-full">
                    <x-icon name="envelope" class="w-4 h-4"/>
                    {{ __('messages.send_otp') }}
                </button>
            </div>

            <div id="step-otp" class="hidden">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.otp_code') }}</label>
                    <input type="text" id="otp-input" class="form-input text-center text-2xl tracking-widest" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                </div>
                <button onclick="verifyOtp()" id="verify-otp-btn" class="btn-primary w-full">
                    <x-icon name="arrow-right-on-rectangle" class="w-4 h-4"/>
                    {{ __('messages.verify') }}
                </button>
                <p class="text-center mt-4">
                    <button onclick="resetForm()" class="text-xs text-primary-600 dark:text-primary-400 font-medium">{{ __('messages.change_phone') }}</button>
                </p>
            </div>

            <div id="step-success" class="hidden text-center">
                <div class="w-14 h-14 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center mx-auto mb-3">
                    <x-icon name="check-circle" class="w-7 h-7 text-primary-600 dark:text-primary-400" strokeWidth="2"/>
                </div>
                <p class="text-sm font-medium text-ink-900 dark:text-white">{{ __('messages.login_successful') }}</p>
            </div>

            <div class="mt-6 text-center border-t border-ink-100 dark:border-ink-700/30 pt-4">
                <a href="{{ route('login') }}" class="text-xs font-medium text-ink-500 dark:text-ink-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    {{ __('messages.email_login_instead') }}
                </a>
            </div>
        </div>
    </div>

    <script>
        let currentPhone = '';

        async function sendOtp() {
            const phone = document.getElementById('phone-input').value.trim();
            if (!phone) return alert('{{ __('messages.enter_phone') }}');

            const btn = document.getElementById('send-otp-btn');
            btn.disabled = true;
            btn.innerHTML = '{{ __('messages.sending') }}...';

            try {
                const res = await fetch('/api/auth/send-otp', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({phone})
                });
                const data = await res.json();
                if (res.ok) {
                    currentPhone = phone;
                    document.getElementById('step-phone').classList.add('hidden');
                    document.getElementById('step-otp').classList.remove('hidden');
                    document.getElementById('otp-input').focus();
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                alert('Network error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '{{ __('messages.send_otp') }}';
            }
        }

        async function verifyOtp() {
            const otp = document.getElementById('otp-input').value.trim();
            if (otp.length !== 6) return alert('{{ __('messages.enter_6_digit_otp') }}');

            const btn = document.getElementById('verify-otp-btn');
            btn.disabled = true;
            btn.innerHTML = '{{ __('messages.verifying') }}...';

            try {
                const res = await fetch('/api/auth/verify-otp', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({phone: currentPhone, otp})
                });
                const data = await res.json();
                if (res.ok) {
                    document.getElementById('step-otp').classList.add('hidden');
                    document.getElementById('step-success').classList.remove('hidden');
                    localStorage.setItem('token', data.token);
                    setTimeout(() => window.location.href = '{{ route("dashboard") }}', 1000);
                } else {
                    alert(data.message || '{{ __('messages.invalid_otp') }}');
                }
            } catch (e) {
                alert('Network error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '{{ __('messages.verify') }}';
            }
        }

        function resetForm() {
            currentPhone = '';
            document.getElementById('step-otp').classList.add('hidden');
            document.getElementById('step-phone').classList.remove('hidden');
            document.getElementById('phone-input').value = '';
            document.getElementById('otp-input').value = '';
        }

        document.getElementById('otp-input').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
            if (this.value.length === 6) verifyOtp();
        });
    </script>
</body>
</html>
