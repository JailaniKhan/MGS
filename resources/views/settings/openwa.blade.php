@extends('layouts.app')

@section('content')
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.whatsapp_gateway') ?? 'WhatsApp Gateway (OpenWA)' }}</h2>
        <a href="{{ route('settings.index') }}" class="text-sm text-primary-600 dark:text-primary-400">&larr; {{ __('messages.settings') }}</a>
    </div>

    @if (!$configured)
        <div class="card p-6 text-center">
            <div class="text-danger-600 dark:text-danger-400 font-medium mb-2">{{ __('messages.openwa_not_configured') ?? 'OpenWA is not configured' }}</div>
            <p class="text-sm text-ink-500 dark:text-ink-400">
                {{ __('messages.openwa_not_configured_hint') ?? 'Set OPENWA_API_KEY and OPENWA_BASE_URL in your .env file to enable WhatsApp reminders via your self-hosted gateway.' }}
            </p>
        </div>
    @else
        <div class="card p-6">
            {{-- Session status badge --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="badge {{ ($status['status'] ?? '') === 'connected' ? 'badge-success' : (($status['status'] ?? '') === 'qr_ready' ? 'badge-warning' : 'badge-danger') }}">
                        {{ ucfirst($status['status'] ?? 'unknown') }}
                    </span>
                    @if (!empty($status['phone']))
                        <span class="text-sm text-ink-500 dark:text-ink-400">{{ $status['phone'] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button id="testSendBtn" type="button" class="btn btn-sm btn-primary">
                        {{ __('messages.openwa_test_send') ?? 'Send test WhatsApp' }}
                    </button>
                    <button id="refreshBtn" type="button" class="btn btn-sm btn-outline">
                        {{ __('messages.refresh') ?? 'Refresh QR' }}
                    </button>
                </div>
            </div>

            <div id="testSendResult" class="text-sm mt-2 hidden"></div>

            {{-- QR code --}}
            <div class="flex flex-col items-center justify-center py-4">
                @if ($qr)
                    <img id="qrImage" src="{{ $qr }}" alt="WhatsApp QR"
                         class="w-64 h-64 rounded-lg border border-ink-200 dark:border-ink-700 bg-white p-2">
                    <p class="text-sm text-ink-500 dark:text-ink-400 mt-4 text-center max-w-xs">
                        {{ __('messages.openwa_scan_hint') ?? 'Open WhatsApp → Linked Devices → Link a device, then scan this code to connect.' }}
                    </p>
                @else
                    <div id="qrPlaceholder" class="w-64 h-64 rounded-lg border border-dashed border-ink-300 dark:border-ink-600 flex items-center justify-center text-center text-sm text-ink-500 dark:text-ink-400 p-4">
                        @if (($status['status'] ?? '') === 'connected')
                            {{ __('messages.openwa_connected') ?? 'Session connected. No QR needed.' }}
                        @else
                            {{ __('messages.openwa_qr_unavailable') ?? 'QR not available right now. Click Refresh.' }}
                        @endif
                    </div>
                @endif
            </div>

            <div class="border-t border-ink-200 dark:border-ink-700 my-4"></div>

            {{-- Pairing code (alternative to QR) --}}
            <div class="flex flex-col items-center justify-center">
                <h4 class="text-sm font-semibold text-ink-700 dark:text-ink-200 mb-1">
                    {{ __('messages.openwa_pairing_title') ?? 'Or link with a phone number' }}
                </h4>
                <p class="text-xs text-ink-500 dark:text-ink-400 mb-3 text-center max-w-xs">
                    {{ __('messages.openwa_pairing_hint') ?? 'Enter your WhatsApp number, generate a code, then type it in WhatsApp → Linked Devices → Link with phone number.' }}
                </p>

                <div class="flex items-center gap-2 w-full max-w-xs">
                    <input id="pairingPhone" type="text" inputmode="numeric" placeholder="93749298490"
                           class="form-input flex-1" value="{{ old('phone', '93749298490') }}">
                    <button id="pairingBtn" type="button" class="btn btn-primary btn-sm whitespace-nowrap">
                        {{ __('messages.openwa_generate_code') ?? 'Generate code' }}
                    </button>
                </div>

                <div id="pairingResult" class="mt-3 text-center hidden">
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.openwa_pairing_code_label') ?? 'Your pairing code:' }}</div>
                    <div id="pairingCode" class="text-2xl font-bold tracking-widest text-primary-600 dark:text-primary-400 my-1">--------</div>
                    <div class="text-xs text-ink-400">{{ __('messages.openwa_pairing_validity') ?? 'Valid for a few minutes. Type it in WhatsApp → Linked Devices → Link with phone number.' }}</div>
                </div>
            </div>

            <div class="text-xs text-ink-400 mt-4 text-center">
                {{ __('messages.openwa_gateway_url') ?? 'Gateway' }}: {{ config('services.openwa.base_url') }}
            </div>
        </div>
    @endif
</div>

@if ($configured)
@push('scripts')
<script>
    document.getElementById('refreshBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('refreshBtn');
        const img = document.getElementById('qrImage');
        const placeholder = document.getElementById('qrPlaceholder');
        btn.disabled = true;
        btn.textContent = '...';

        try {
            const res = await fetch('{{ route('settings.openwa.qr') }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (data.qr) {
                if (!img) {
                    // Create image if only placeholder exists
                    const newImg = document.createElement('img');
                    newImg.id = 'qrImage';
                    newImg.alt = 'WhatsApp QR';
                    newImg.className = 'w-64 h-64 rounded-lg border border-ink-200 dark:border-ink-700 bg-white p-2';
                    placeholder.replaceWith(newImg);
                }
                document.getElementById('qrImage').src = data.qr;
            }
        } catch (e) {
            console.error(e);
        } finally {
            btn.disabled = false;
            btn.textContent = '{{ __('messages.refresh') ?? 'Refresh QR' }}';
        }
    });

    document.getElementById('pairingBtn')?.addEventListener('click', async () => {
        const phone = document.getElementById('pairingPhone').value.trim();
        const result = document.getElementById('pairingResult');
        const codeEl = document.getElementById('pairingCode');

        if (!phone) {
            alert('{{ __('messages.openwa_enter_phone') ?? 'Please enter your WhatsApp number.' }}');
            return;
        }

        btn.disabled = true;
        btn.textContent = '...';

        try {
            const res = await fetch('{{ route('settings.openwa.pairing') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                },
                body: JSON.stringify({ phone })
            });
            const data = await res.json();

            if (data.pairingCode) {
                codeEl.textContent = data.pairingCode;
                result.classList.remove('hidden');
            } else {
                alert(data.error || '{{ __('messages.openwa_pairing_error') ?? 'Could not generate pairing code.' }}');
            }
        } catch (e) {
            console.error(e);
            alert('{{ __('messages.openwa_pairing_error') ?? 'Could not generate pairing code.' }}');
        } finally {
            btn.disabled = false;
            btn.textContent = '{{ __('messages.openwa_generate_code') ?? 'Generate code' }}';
        }
    });

    document.getElementById('testSendBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('testSendBtn');
        const result = document.getElementById('testSendResult');
        btn.disabled = true;
        btn.textContent = '...';

        try {
            const res = await fetch('{{ route('settings.openwa.test') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                }
            });
            const data = await res.json();

            result.classList.remove('hidden');
            if (data.success) {
                result.className = 'text-sm mt-2 text-primary-600 dark:text-primary-400';
                result.textContent = '{{ __('messages.openwa_test_sent') ?? 'Test message sent! Check your WhatsApp.' }}';
            } else {
                result.className = 'text-sm mt-2 text-danger-600 dark:text-danger-400';
                result.textContent = '{{ __('messages.openwa_test_failed') ?? 'Test failed:' }} ' + (data.error || '');
            }
        } catch (e) {
            console.error(e);
            result.classList.remove('hidden');
            result.className = 'text-sm mt-2 text-danger-600 dark:text-danger-400';
            result.textContent = '{{ __('messages.openwa_test_failed') ?? 'Test failed:' }} request error';
        } finally {
            btn.disabled = false;
            btn.textContent = '{{ __('messages.openwa_test_send') ?? 'Send test WhatsApp' }}';
        }
    });
</script>
@endpush
@endif
@endsection
