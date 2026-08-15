@extends('layouts.auth')

@section('title', __('messages.phone_login'))

@section('content')
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ __('messages.phone_login') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.enter_phone_for_otp') }}</p>

    <div id="step-phone">
        <div class="mb-4">
            <label class="form-label">{{ __('messages.phone') }}</label>
            <input type="tel" id="phone-input" class="form-input" placeholder="+93 XXX XXX XXX" autocomplete="tel">
            <p id="phone-error" class="hidden text-xs font-medium text-danger-600 dark:text-danger-400 mt-1.5" role="alert"></p>
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
            <p id="otp-error" class="hidden text-xs font-medium text-danger-600 dark:text-danger-400 mt-1.5" role="alert"></p>
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
@endsection

@push('scripts')
    <script>
        let currentPhone = '';

        function showError(id, message) {
            const el = document.getElementById(id);
            el.textContent = message;
            el.classList.remove('hidden');
        }

        function clearError(id) {
            const el = document.getElementById(id);
            el.textContent = '';
            el.classList.add('hidden');
        }

        async function sendOtp() {
            const phone = document.getElementById('phone-input').value.trim();
            clearError('phone-error');
            if (!phone) return showError('phone-error', '{{ __('messages.enter_phone') }}');

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
                    showError('phone-error', data.message || '{{ __('messages.invalid_phone') }}');
                }
            } catch (e) {
                showError('phone-error', '{{ __('messages.network_error') }}');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '{{ __('messages.send_otp') }}';
            }
        }

        async function verifyOtp() {
            const otp = document.getElementById('otp-input').value.trim();
            clearError('otp-error');
            if (otp.length !== 6) return showError('otp-error', '{{ __('messages.enter_6_digit_otp') }}');

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
                    showError('otp-error', data.message || '{{ __('messages.invalid_otp') }}');
                }
            } catch (e) {
                showError('otp-error', '{{ __('messages.network_error') }}');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '{{ __('messages.verify') }}';
            }
        }

        function resetForm() {
            currentPhone = '';
            clearError('phone-error');
            clearError('otp-error');
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
@endpush
