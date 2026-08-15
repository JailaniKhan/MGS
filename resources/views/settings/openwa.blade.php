@extends('layouts.app')

@section('content')
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.whatsapp_gateway') ?? 'WhatsApp Gateway (OpenWA)' }}</h2>
        <div class="flex items-center gap-3">
            <a href="{{ route('whatsapp.chats.index') }}" class="text-sm text-primary-600 dark:text-primary-400">{{ __('messages.whatsapp_chats') }}</a>
            <a href="{{ route('settings.index') }}" class="text-sm text-primary-600 dark:text-primary-400">&larr; {{ __('messages.settings') }}</a>
        </div>
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
        @php
            $rawState = $status['status'] ?? null;
            $isReachable = $status !== null;
            $badgeClass = match (true) {
                in_array($rawState, ['connected', 'ready']) => 'badge-success',
                $rawState === 'qr_ready' => 'badge-warning',
                !$isReachable => 'badge-danger',
                default => 'badge-danger',
            };
            $badgeLabel = $isReachable ? ucfirst($rawState ?? 'unknown') : 'Unreachable';
        @endphp

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    @isset($gatewayRunning)
                        <span class="badge {{ $gatewayRunning ? 'badge-success' : 'badge-danger' }}">
                            {{ $gatewayRunning ? __('messages.openwa_gateway_running') ?? 'Gateway: Running' : __('messages.openwa_gateway_stopped') ?? 'Gateway: Stopped' }}
                        </span>
                    @endisset
                    <span id="sessionBadge" class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
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
                    <button id="restartBtn" type="button" class="btn btn-sm btn-danger">
                        {{ __('messages.openwa_restart') ?? 'Restart gateway' }}
                    </button>
                </div>
            </div>

            <div id="testSendResult" class="text-sm mt-2 hidden"></div>

            @if (!$isReachable)
                <div class="text-sm text-danger-600 dark:text-danger-400 bg-danger-50 dark:bg-danger-900/20 rounded-lg p-3 mb-4">
                    {{ __('messages.openwa_unreachable') ?? 'OpenWA gateway is not reachable. Make sure it is running (it auto-starts on login) and OPENWA_BASE_URL is correct.' }}
                </div>

                @if (!empty($managerStatus['last_error']))
                    <div id="openwaDiagnostics" class="text-xs bg-ink-50 dark:bg-ink-800/50 rounded-lg p-3 mb-4 font-mono whitespace-pre-wrap break-words">
                        <div class="font-semibold text-ink-700 dark:text-ink-200 mb-1">{{ __('messages.openwa_diag') ?? 'Gateway diagnostic (last launch attempt):' }}</div>
                        <div id="openwaLastError" class="text-ink-600 dark:text-ink-300">{{ $managerStatus['last_error'] }}</div>
                        <div class="mt-2 border-t border-ink-200 dark:border-ink-700 pt-2">
                            <div>{{ __('messages.openwa_diag_app_dir') ?? 'App dir' }}: {{ $managerStatus['resolved_app_dir'] ?? '(none)' }}</div>
                            <div>{{ __('messages.openwa_diag_node') ?? 'Node binary' }}: {{ $managerStatus['resolved_node_binary'] ?? '(none)' }}</div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Why the session isn't connected: rendered from lastDisconnect,
                 which the gateway exposes with reason + message. Distinguishes
                 a 401 (rejected credentials — wipe & re-link) from a transient
                 408 (network/DNS or plain QR expiry) so the user knows whether
                 to fix their WhatsApp account or just wait. Rendered server-side
                 on first paint; the JS poll keeps it fresh. --}}
            @php
                $discReason = $status['lastDisconnect']['reason'] ?? null;
                $discReason = $discReason === null ? null : (int) $discReason;
                $discCls = 'bg-ink-50 dark:bg-ink-800/50 text-ink-600 dark:text-ink-300';
                $discText = __('messages.openwa_disc_generic');
                if ($discReason === 401) {
                    $discCls = 'bg-danger-50 dark:bg-danger-900/20 text-danger-600 dark:text-danger-400';
                    $discText = __('messages.openwa_disc_401');
                } elseif ($discReason === 408) {
                    $discCls = 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400';
                    $discText = __('messages.openwa_disc_408');
                }
            @endphp
            <div id="disconnectNote" class="text-sm rounded-lg p-3 mb-4 {{ $discCls }}" {{ $discReason === null ? 'hidden' : '' }}>{{ $discText }}</div>

            {{-- QR + pairing code side by side: both are generated together so
                 the user can scan the QR OR type the code — whichever they
                 reach first. On narrow screens they stack. --}}
            <div class="grid md:grid-cols-2 gap-6">

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
                            @if (in_array($status['status'] ?? '', ['connected', 'ready'], true))
                                {{ __('messages.openwa_connected') ?? 'Session connected. No QR needed.' }}
                            @else
                                {{ __('messages.openwa_qr_unavailable') ?? 'QR not available right now. Click Refresh.' }}
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Pairing code (alternative to QR) --}}
                <div class="flex flex-col items-center justify-center py-4">
                    <h4 class="text-sm font-semibold text-ink-700 dark:text-ink-200 mb-1">
                        {{ __('messages.openwa_pairing_title') ?? 'Or link with a phone number' }}
                    </h4>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mb-3 text-center max-w-xs">
                        {{ __('messages.openwa_pairing_hint') ?? 'Enter your WhatsApp number — the code is generated together with the QR so you can link either way.' }}
                    </p>

                    <div class="flex items-center gap-2 w-full max-w-xs">
                        <input id="pairingPhone" type="text" inputmode="numeric" placeholder="93700268836"
                               class="form-input flex-1">
                        <button id="pairingBtn" type="button" class="btn btn-primary btn-sm whitespace-nowrap">
                            {{ __('messages.openwa_generate_code') ?? 'Generate code' }}
                        </button>
                    </div>

                    <div id="pairingResult" class="mt-3 text-center">
                        <div class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.openwa_pairing_code_label') ?? 'Your pairing code:' }}</div>
                        <div id="pairingCode" class="text-2xl font-bold tracking-widest text-primary-600 dark:text-primary-400 my-1">--------</div>
                        <div id="pairingExpiry" class="text-xs font-medium hidden"></div>
                        <div class="text-xs text-ink-400">{{ __('messages.openwa_pairing_validity') ?? 'Valid for a few minutes. Type it in WhatsApp → Linked Devices → Link with phone number.' }}</div>
                    </div>
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
    const QR_PLACEHOLDER_CLASS = 'w-64 h-64 rounded-lg border border-dashed border-ink-300 dark:border-ink-600 flex items-center justify-center text-center text-sm text-ink-500 dark:text-ink-400 p-4';

    function showPlaceholder(text) {
        const img = document.getElementById('qrImage');
        const placeholder = document.getElementById('qrPlaceholder');
        if (img && !placeholder) {
            const div = document.createElement('div');
            div.id = 'qrPlaceholder';
            div.className = QR_PLACEHOLDER_CLASS;
            img.replaceWith(div);
            div.textContent = text;
        } else if (placeholder) {
            placeholder.textContent = text;
        }
    }

    function setBadge(status) {
        const badge = document.getElementById('sessionBadge');
        if (!badge) return;
        const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unreachable';
        const cls = ['connected', 'ready'].includes(status)
            ? 'badge-success'
            : status === 'qr_ready'
                ? 'badge-warning'
                : 'badge-danger';
        badge.textContent = label;
        badge.className = 'badge ' + cls;
    }

    // Guard: QR polls and manual refreshes must never overlap (the retry
    // inside refreshState can take up to ~5s; overlapping calls would show
    // a QR that is already seconds stale).
    let refreshing = false;

    async function refreshState() {
        if (refreshing) return;
        refreshing = true;
        try {
            let res = await fetch('{{ route('settings.openwa.qr') }}', { headers: { 'Accept': 'application/json' } });
            let data = await res.json();

            setBadge(data.status);
            renderDisconnect(data.last_disconnect);

            // After a kick (start) the fresh socket needs ~2-4s to generate
            // its first QR, so retry once before giving up.
            if (!data.qr && data.status !== 'connected' && data.status !== 'dead') {
                await new Promise(r => setTimeout(r, 3000));
                res = await fetch('{{ route('settings.openwa.qr') }}', { headers: { 'Accept': 'application/json' } });
                data = await res.json();
                setBadge(data.status);
                renderDisconnect(data.last_disconnect);
            }

            const diag = document.getElementById('openwaDiagnostics');
            if (diag && data.last_error) {
                const errEl = document.getElementById('openwaLastError');
                if (errEl) errEl.textContent = data.last_error;
            }

            const img = document.getElementById('qrImage');
            if (data.qr) {
                if (!img) {
                    const placeholder = document.getElementById('qrPlaceholder');
                    const newImg = document.createElement('img');
                    newImg.id = 'qrImage';
                    newImg.alt = 'WhatsApp QR';
                    newImg.className = 'w-64 h-64 rounded-lg border border-ink-200 dark:border-ink-700 bg-white p-2';
                    if (placeholder) placeholder.replaceWith(newImg);
                    else document.querySelector('.flex.flex-col.items-center.justify-center.py-4').appendChild(newImg);
                }
                document.getElementById('qrImage').src = data.qr;
            } else if (img) {
                // QR rotated/expired — drop the stale image so a dead code
                // can't be scanned; the next poll will re-add a fresh one.
                const div = document.createElement('div');
                div.id = 'qrPlaceholder';
                div.className = QR_PLACEHOLDER_CLASS;
                const linked = ['connected', 'ready'].includes(data.status);
                div.textContent = linked
                    ? '{{ __('messages.openwa_connected') ?? 'Session connected. No QR needed.' }}'
                    : '{{ __('messages.openwa_qr_unavailable') ?? 'QR not available right now. Click Refresh.' }}';
                img.replaceWith(div);
            } else {
                showPlaceholder(['connected', 'ready'].includes(data.status)
                    ? '{{ __('messages.openwa_connected') ?? 'Session connected. No QR needed.' }}'
                    : '{{ __('messages.openwa_qr_unavailable') ?? 'QR not available right now. Click Refresh.' }}');
            }
        } catch (e) {
            console.error(e);
        } finally {
            refreshing = false;
        }
    }

    document.getElementById('refreshBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('refreshBtn');
        btn.disabled = true;
        btn.textContent = '...';
        await refreshState();
        btn.disabled = false;
        btn.textContent = '{{ __('messages.refresh') ?? 'Refresh QR' }}';
    });

    // The gateway rotates the QR every ~20s. Auto-poll at 10s so the
    // on-screen code is never stale when the user raises the camera —
    // scanning an expired code silently fails ("Couldn't link device").
    setInterval(refreshState, 10000);

    document.getElementById('pairingBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('pairingBtn');
        const phone = document.getElementById('pairingPhone').value.trim();
        const result = document.getElementById('pairingResult');
        const codeEl = document.getElementById('pairingCode');

        if (!phone) {
            showToast('error', '{{ __('messages.openwa_enter_phone') ?? 'Please enter your WhatsApp number.' }}');
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
                startPairingCountdown(data.expiresAt);
                startPairingPoll();
            } else {
                showToast('error', data.error || '{{ __('messages.openwa_pairing_error') ?? 'Could not generate pairing code.' }}');
            }
        } catch (e) {
            console.error(e);
            showToast('error', '{{ __('messages.openwa_pairing_error') ?? 'Could not generate pairing code.' }}');
        } finally {
            btn.disabled = false;
            btn.textContent = '{{ __('messages.openwa_generate_code') ?? 'Generate code' }}';
        }
    });

    document.getElementById('restartBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('restartBtn');
        btn.disabled = true;
        btn.textContent = '{{ __('messages.openwa_restarting') ?? 'Restarting…' }}';

        try {
            const res = await fetch('{{ route('settings.openwa.restart') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                }
            });
            const data = await res.json();
            showToast(data.success ? 'success' : 'error',
                data.success
                    ? '{{ __('messages.openwa_restarted') ?? 'Gateway restarted.' }}'
                    : (data.error || '{{ __('messages.openwa_restart_failed') ?? 'Restart failed.' }}'));
        } catch (e) {
            console.error(e);
            showToast('error', '{{ __('messages.openwa_restart_failed') ?? 'Restart failed.' }}');
        } finally {
            btn.disabled = false;
            btn.textContent = '{{ __('messages.openwa_restart') ?? 'Restart gateway' }}';
            await refreshState();
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

    // ---- Disconnect diagnostics -------------------------------------------
    // The gateway records WHY the session dropped (reason + message). A 401
    // means WhatsApp rejected the stored credentials (linked-device limit,
    // manual logout, phone number changed) and the only fix is re-linking;
    // a 408 is a transient network/DNS blip or a QR that expired without a
    // scan — the gateway recovers on its own, so just wait.
    function renderDisconnect(lastDisconnect) {
        const el = document.getElementById('disconnectNote');
        if (!el) return;
        if (!lastDisconnect || lastDisconnect.reason === undefined || lastDisconnect.reason === null) {
            el.classList.add('hidden');
            return;
        }
        const reason = Number(lastDisconnect.reason);
        let cls = 'bg-ink-50 dark:bg-ink-800/50 text-ink-600 dark:text-ink-300';
        let text = '{{ __('messages.openwa_disc_generic') ?? 'Connection lost — reconnecting…' }}';
        if (reason === 401) {
            cls = 'bg-danger-50 dark:bg-danger-900/20 text-danger-600 dark:text-danger-400';
            text = '{{ __('messages.openwa_disc_401') ?? 'WhatsApp rejected the session (401). Stored credentials were wiped — link the device again. If it keeps failing, open WhatsApp on your phone, go to Linked Devices and remove old entries (max 4), then wait a few minutes before retrying.' }}';
        } else if (reason === 408) {
            cls = 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400';
            text = '{{ __('messages.openwa_disc_408') ?? 'Connection lost (408) — a network or DNS problem, or the QR/pairing code expired. The gateway recovers automatically; wait a moment or press Restart.' }}';
        }
        el.className = 'text-sm rounded-lg p-3 mb-4 ' + cls;
        el.textContent = text;
        el.classList.remove('hidden');
    }

    // ---- Pairing code countdown + auto-refresh ----------------------------
    // Pairing codes die with the socket (~60-90s). Poll the gateway every 2s
    // so the countdown is honest and, when the code expires, a fresh one is
    // requested automatically — the code on screen never outlives its TTL.
    let pairingPollTimer = null;
    let pairingCountdownTimer = null;
    let pairingPhone = null;

    function startPairingPoll() {
        stopPairingPoll();
        pairingPhone = document.getElementById('pairingPhone').value.trim() || pairingPhone;
        pairingPollTimer = setInterval(async () => {
            try {
                // Session linked — the code dies with its socket. Stop polling
                // instead of POSTing fresh codes into a connected session.
                const badge = document.getElementById('sessionBadge');
                if (badge && badge.classList.contains('badge-success')) { stopPairingPoll(); return; }
                const res = await fetch('{{ route('settings.openwa.pairing-status') }}', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const result = document.getElementById('pairingResult');
                if (!result || result.classList.contains('hidden')) { stopPairingPoll(); return; }
                if (data.pairingCode) {
                    document.getElementById('pairingCode').textContent = data.pairingCode;
                    startPairingCountdown(data.expiresAt);
                } else {
                    await requestNewPairingCode(true);
                }
            } catch (e) {
                console.error(e);
            }
        }, 2000);
    }

    function stopPairingPoll() {
        if (pairingPollTimer) { clearInterval(pairingPollTimer); pairingPollTimer = null; }
    }

    function startPairingCountdown(expiresAt) {
        const el = document.getElementById('pairingExpiry');
        if (!el) return;
        el.classList.remove('hidden');
        if (pairingCountdownTimer) clearInterval(pairingCountdownTimer);
        const tick = () => {
            const secs = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
            el.textContent = secs > 0
                ? '{{ __('messages.openwa_pairing_expires_in') ?? 'Expires in' }} ' + secs + 's'
                : '{{ __('messages.openwa_pairing_expired') ?? 'Code expired — generating a new one…' }}';
        };
        tick();
        pairingCountdownTimer = setInterval(tick, 1000);
    }

    // Guard against concurrent generations: the 2s poll and a manual button
    // click must never POST at the same time (that produced two codes 137ms
    // apart on 8/4 11:18:36). Once a request is in flight, drop the others.
    let generatingPairing = false;

    async function requestNewPairingCode(silent) {
        if (generatingPairing) return;
        generatingPairing = true;
        const btn = document.getElementById('pairingBtn');
        const codeEl = document.getElementById('pairingCode');
        const expiryEl = document.getElementById('pairingExpiry');
        if (!pairingPhone) { generatingPairing = false; return; }
        if (!silent) btn.disabled = true;
        // The old code died with its socket — never leave it on screen and
        // typable during the ~2-5s reconnect. Blank it and say what's
        // happening so the user stops typing a dead code.
        codeEl.textContent = '--------';
        if (expiryEl) {
            expiryEl.classList.remove('hidden');
            expiryEl.textContent = '{{ __('messages.openwa_pairing_generating') ?? 'Generating new code…' }}';
        }
        try {
            const res = await fetch('{{ route('settings.openwa.pairing') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                },
                body: JSON.stringify({ phone: pairingPhone })
            });
            const data = await res.json();
            if (data.pairingCode) {
                // Stop any stale countdown before starting the new one.
                if (pairingCountdownTimer) clearInterval(pairingCountdownTimer);
                codeEl.textContent = data.pairingCode;
                startPairingCountdown(data.expiresAt);
                startPairingPoll();
            } else {
                // No code returned — let the auto-retry interval try again.
                autoPairingRequested = false;
                if (!silent) showToast('error', data.error || '{{ __('messages.openwa_pairing_error') ?? 'Could not generate pairing code.' }}');
            }
        } catch (e) {
            console.error(e);
            // Allow the auto-request interval to retry a failed generation.
            autoPairingRequested = false;
            if (!silent) showToast('error', '{{ __('messages.openwa_pairing_error') ?? 'Could not generate pairing code.' }}');
        } finally {
            generatingPairing = false;
            if (!silent) btn.disabled = false;
        }
    }

    // ---- Auto-generate the pairing code together with the QR --------------
    // No button press needed: as soon as a QR is on screen, request a code so
    // the QR and the pairing code appear at the same time. Fires once per page
    // (guarded by autoPairingRequested); from then on the 2s pairing poll owns
    // renewal. Skips while a generation is in flight and while the session is
    // already linked (no QR).
    let autoPairingRequested = false;

    function maybeAutoRequestPairing() {
        if (autoPairingRequested || generatingPairing) return;
        // Already linked (no QR) or no number to request for → nothing to do.
        const img = document.getElementById('qrImage');
        const phone = document.getElementById('pairingPhone')?.value?.trim();
        const codeEl = document.getElementById('pairingCode');
        if (!img || !phone || codeEl?.textContent !== '--------') return;
        autoPairingRequested = true;
        pairingPhone = phone;
        requestNewPairingCode(true).then(() => refreshState());
    }

    // Fire on load; refreshState() re-fires the same guard later for the case
    // where the gateway was still starting when the page rendered (no QR yet).
    maybeAutoRequestPairing();
    setInterval(maybeAutoRequestPairing, 10000);
</script>
@endpush
@endif
@endsection
