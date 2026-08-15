<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ps', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MGS - {{ __('messages.lock_screen') }}</title>
    @fonts
    @if(in_array(app()->getLocale(), ['ps', 'fa']))
        @vite(['resources/css/app-rtl.css'])
    @else
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="bg-[#0b0c0e] text-white min-h-screen flex items-center justify-center font-sans antialiased">
    <div class="auth-ambient" aria-hidden="true"></div>

    <div class="relative z-10 w-full max-w-sm px-6">
        <div class="text-center mb-9">
            <div class="w-16 h-16 rounded-2xl brand-grad text-white flex items-center justify-center mx-auto mb-4 shadow-fab">
                <x-icon name="lock-closed" class="w-7 h-7" strokeWidth="1.8"/>
            </div>
            <h1 class="text-xl font-extrabold tracking-tight">MGS</h1>
            <p class="text-ink-400 text-sm mt-1.5">{{ __('messages.enter_pin_to_unlock') }}</p>
        </div>

        <form id="pin-verify-form" class="space-y-7">
            <div class="flex justify-center gap-3" id="pin-display" dir="ltr">
                <input type="password" maxlength="1" class="pin-input" data-index="0" inputmode="numeric" autocomplete="off" aria-label="PIN 1">
                <input type="password" maxlength="1" class="pin-input" data-index="1" inputmode="numeric" autocomplete="off" aria-label="PIN 2">
                <input type="password" maxlength="1" class="pin-input" data-index="2" inputmode="numeric" autocomplete="off" aria-label="PIN 3">
                <input type="password" maxlength="1" class="pin-input" data-index="3" inputmode="numeric" autocomplete="off" aria-label="PIN 4">
            </div>

            <div id="pin-error" class="text-center text-danger-400 text-sm font-medium hidden">{{ __('messages.invalid_pin') }}</div>

            <button type="submit" class="btn-primary w-full py-4">
                <x-icon name="lock-open" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.unlock') }}
            </button>
        </form>
    </div>

    <style>
        .pin-input {
            width: 58px;
            height: 62px;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            border-radius: 1rem;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.05);
            color: white;
            outline: none;
            transition: all 0.2s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .pin-input:focus {
            border-color: var(--color-primary-400, #46bf7c);
            box-shadow: 0 0 0 3px rgb(16 174 100 / 0.22);
            background: rgba(16,174,100,0.08);
        }
        .pin-input.filled {
            border-color: rgba(16,174,100,0.45);
        }
        #pin-display.shake { animation: pinShake 0.3s ease; }
        @keyframes pinShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
    </style>

    <script>
        const inputs = document.querySelectorAll('.pin-input');
        inputs[0].focus();

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
                e.target.classList.toggle('filled', !!e.target.value);
                if (e.target.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        document.getElementById('pin-verify-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const pin = Array.from(inputs).map(i => i.value).join('');
            if (pin.length !== 4) return;

            const response = await fetch('{{ route("app-lock.verify-pin") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ pin })
            });

            const result = await response.json();
            if (result.success) {
                window.location.href = '{{ route("dashboard") }}';
            } else {
                const wrap = document.getElementById('pin-display');
                document.getElementById('pin-error').classList.remove('hidden');
                inputs.forEach(i => { i.value = ''; i.classList.remove('filled'); });
                inputs[0].focus();
                wrap.classList.add('shake');
                setTimeout(() => wrap.classList.remove('shake'), 350);
            }
        });
    </script>
</body>
</html>
