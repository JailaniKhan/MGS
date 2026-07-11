<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ps', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MGS - {{ __('messages.lock_screen') }}</title>
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm px-6">
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-brand text-white flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-500/30">
                <span class="text-white font-bold text-2xl">M</span>
            </div>
            <h1 class="text-xl font-bold">MGS</h1>
            <p class="text-gray-400 text-sm mt-1">{{ __('messages.enter_pin_to_unlock') }}</p>
        </div>

        <form id="pin-verify-form" class="space-y-6">
            <div class="flex justify-center gap-3" id="pin-display">
                <input type="text" maxlength="1" class="pin-input" data-index="0" inputmode="numeric">
                <input type="text" maxlength="1" class="pin-input" data-index="1" inputmode="numeric">
                <input type="text" maxlength="1" class="pin-input" data-index="2" inputmode="numeric">
                <input type="text" maxlength="1" class="pin-input" data-index="3" inputmode="numeric">
            </div>

            <div id="pin-error" class="text-center text-red-400 text-sm hidden">{{ __('messages.invalid_pin') }}</div>

            <button type="submit" class="btn-primary w-full">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
                {{ __('messages.unlock') }}
            </button>
        </form>
    </div>

    <style>
        .pin-input {
            width: 56px;
            height: 56px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border-radius: 16px;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: white;
            outline: none;
            transition: all 0.2s;
        }
        .pin-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
    </style>

    <script>
        const inputs = document.querySelectorAll('.pin-input');
        inputs[0].focus();

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
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
                document.getElementById('pin-error').classList.remove('hidden');
                inputs.forEach(i => { i.value = ''; });
                inputs[0].focus();
            }
        });
    </script>
</body>
</html>
