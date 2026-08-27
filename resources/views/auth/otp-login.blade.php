@extends('layouts.auth')

@section('title', __('messages.phone_login'))

@section('content')
    <h2 class="text-[1.625rem] font-extrabold tracking-tight text-ink-900 dark:text-white leading-tight">{{ __('messages.phone_login') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 mb-7">{{ __('messages.enter_phone_for_otp') }}</p>

    <div id="step-phone">
        <div class="mb-5">
            <label for="phone-input" class="form-label">{{ __('messages.phone') }}</label>
            <div class="relative" dir="ltr">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="device-phone-mobile" class="w-4 h-4"/>
                </span>
                <input type="tel" id="phone-input" class="form-input ps-11" placeholder="+93 XXX XXX XXX" autocomplete="tel" dir="ltr">
            </div>
            <p id="phone-error" class="hidden text-xs font-medium text-danger-600 dark:text-danger-400 mt-2" role="alert"></p>
        </div>
        <button onclick="sendOtp()" id="send-otp-btn" class="btn-primary w-full py-4">
            <x-icon name="envelope" class="w-4 h-4"/>
            {{ __('messages.send_otp') }}
        </button>
    </div>

    <div id="step-otp" class="hidden">
        <div class="mb-5">
            <label class="form-label text-center w-full">{{ __('messages.otp_code') }}</label>
            <div class="flex justify-center gap-2.5 my-4" id="otp-display" dir="ltr">
                <input type="text" maxlength="1" class="otp-cell" data-index="0" inputmode="numeric" autocomplete="one-time-code">
                <input type="text" maxlength="1" class="otp-cell" data-index="1" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-cell" data-index="2" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-cell" data-index="3" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-cell" data-index="4" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-cell" data-index="5" inputmode="numeric">
            </div>
            <p id="otp-error" class="hidden text-xs font-medium text-danger-600 dark:text-danger-400 mt-2 text-center" role="alert"></p>
        </div>
        <button onclick="verifyOtp()" id="verify-otp-btn" class="btn-primary w-full py-4">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.verify') }}
        </button>
        <p class="text-center mt-5">
            <button onclick="resetForm()" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">{{ __('messages.change_phone') }}</button>
        </p>
    </div>

    <div id="step-success" class="hidden text-center py-6">
        <div class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center mx-auto mb-4">
            <x-icon name="check-circle" class="w-8 h-8 text-primary-600 dark:text-primary-400" strokeWidth="2"/>
        </div>
        <p class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.login_successful') }}</p>
    </div>

    <div class="mt-6 text-center border-t border-ink-100 dark:border-white/[0.06] pt-5">
        <a href="{{ route('login') }}" class="text-xs font-semibold text-ink-500 dark:text-ink-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
            {{ __('messages.email_login_instead') }}
        </a>
    </div>

    <style>
        .otp-cell {
            width: 46px;
            height: 54px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            border-radius: 0.75rem;
            border: 1px solid var(--color-ink-200);
            background: var(--color-ink-50);
            color: var(--color-ink-900);
            outline: none;
            transition: all 0.2s var(--ease-smooth, ease);
        }
        .dark .otp-cell {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: #fff;
        }
        .otp-cell:focus {
            border-color: var(--color-primary-400);
            box-shadow: 0 0 0 3px rgb(16 174 100 / 0.2);
        }
        .otp-cell.shake { animation: otpShake 0.3s ease; }
        @keyframes otpShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
    </style>
@endsection

@push('scripts')
    <script>
        let currentPhone = '';

        const otpCells = Array.from(document.querySelectorAll('.otp-cell'));
        otpCells.forEach((cell, i) => {
            cell.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
                if (e.target.value && i < otpCells.length - 1) otpCells[i + 1].focus();
                if (getOtp().length === 6) verifyOtp();
            });
            cell.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && i > 0) otpCells[i - 1].focus();
            });
            cell.addEventListener('paste', (e) => {
                const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!text) return;
                e.preventDefault();
                text.split('').forEach((c, j) => { if (otpCells[j]) otpCells[j].value = c; });
                otpCells[Math.min(text.length, otpCells.length) - 1].focus();
                verifyOtp();
            });
        });

        function getOtp() {
            return otpCells.map(c => c.value).join('');
        }

        function shakeOtp() {
            const wrap = document.getElementById('otp-display');
            wrap.classList.add('shake');
            setTimeout(() => wrap.classList.remove('shake'), 350);
        }

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
            btn.textContent = '{{ __('messages.sending') }}';

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
                    otpCells[0].focus();
                } else {
                    showError('phone-error', data.message || '{{ __('messages.invalid_phone') }}');
                }
            } catch (e) {
                showError('phone-error', '{{ __('messages.network_error') }}');
            } finally {
                btn.disabled = false;
                btn.textContent = '{{ __('messages.send_otp') }}';
            }
        }

        async function verifyOtp() {
            const otp = getOtp();
            clearError('otp-error');
            if (otp.length !== 6) return showError('otp-error', '{{ __('messages.enter_6_digit_otp') }}');

            const btn = document.getElementById('verify-otp-btn');
            btn.disabled = true;
            btn.textContent = '{{ __('messages.verifying') }}';

            try {
                const res = await fetch('{{ route('login.phone.verify') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({phone: currentPhone, otp})
                });
                const data = await res.json();
                if (res.ok) {
                    document.getElementById('step-otp').classList.add('hidden');
                    document.getElementById('step-success').classList.remove('hidden');
                    setTimeout(() => window.location.href = data.redirect, 800);
                } else {
                    showError('otp-error', data.message || '{{ __('messages.invalid_otp') }}');
                    shakeOtp();
                    otpCells.forEach(c => { c.value = ''; });
                    otpCells[0].focus();
                }
            } catch (e) {
                showError('otp-error', '{{ __('messages.network_error') }}');
                shakeOtp();
            } finally {
                btn.disabled = false;
                btn.textContent = '{{ __('messages.verify') }}';
            }
        }

        function resetForm() {
            currentPhone = '';
            clearError('phone-error');
            clearError('otp-error');
            document.getElementById('step-otp').classList.add('hidden');
            document.getElementById('step-phone').classList.remove('hidden');
            document.getElementById('phone-input').value = '';
            otpCells.forEach(c => { c.value = ''; });
        }
    </script>
@endpush
