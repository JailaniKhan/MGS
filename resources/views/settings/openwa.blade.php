@extends('layouts.app')

@section('content')
<div class="page-enter">
    {{-- Header --}}
    <div class="page-header">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="chat-bubble-left-right" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="page-title leading-tight truncate">{{ __('messages.whatsapp_gateway') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate" dir="ltr">{{ config('services.openwa.base_url') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('whatsapp.chats.index') }}" aria-label="{{ __('messages.whatsapp_chats') }}"
               class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
                <x-icon name="chat-bubble-oval-left-ellipsis" class="w-4 h-4" strokeWidth="1.8"/>
            </a>
            <a href="{{ route('settings.index') }}" aria-label="{{ __('messages.back') }}"
               class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
                <x-icon name="arrow-left" class="w-4 h-4 back-chevron" strokeWidth="2"/>
            </a>
        </div>
    </div>

    @if (!$configured)
        <div class="card p-6 text-center">
            <div class="text-danger-600 dark:text-danger-400 font-medium mb-2">{{ __('messages.openwa_not_configured') }}</div>
            <p class="text-sm text-ink-500 dark:text-ink-400">
                {{ __('messages.openwa_not_configured_hint') }}
            </p>
        </div>
    @else
        @php
            $rawState = $status['status'] ?? null;
            $isReachable = $status !== null;
            $badgeClass = match (true) {
                in_array($rawState, ['connected', 'ready']) => 'badge-success',
                $rawState === 'qr_ready' => 'badge-warning',
                !$isReachable => 'badge-danger',
                default => 'badge-danger',
            };
            $badgeLabel = match (true) {
                $rawState === 'connected' => __('messages.openwa_status_connected'),
                $rawState === 'ready' => __('messages.openwa_status_ready'),
                $rawState === 'qr_ready' => __('messages.openwa_status_qr_ready'),
                !$isReachable => __('messages.openwa_status_unreachable'),
                default => __('messages.openwa_status_unknown'),
            };
        @endphp

        {{-- Status strip --}}
        <div class="card px-4 py-3.5 mb-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 min-w-0">
                    <span id="sessionBadge" class="badge {{ $badgeClass }} flex-shrink-0">{{ $badgeLabel }}</span>
                    @isset($gatewayRunning)
                        <span class="badge {{ $gatewayRunning ? 'badge-success' : 'badge-danger' }} flex-shrink-0">
                            {{ $gatewayRunning ? __('messages.openwa_gateway_running') : __('messages.openwa_gateway_stopped') }}
                        </span>
                    @endisset
                    @if (!empty($status['phone']))
                        <span class="text-sm font-semibold text-ink-700 dark:text-ink-300 truncate" dir="ltr">{{ $status['phone'] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button id="testSendBtn" type="button" class="btn btn-sm btn-primary">
                        <span class="btn-label">{{ __('messages.openwa_test_send') }}</span>
                    </button>
                    <button id="refreshBtn" type="button" class="btn btn-sm btn-secondary">
                        <x-icon name="arrow-path" class="w-3.5 h-3.5"/>
                        <span class="btn-label">{{ __('messages.refresh') }}</span>
                    </button>
                    <button id="restartBtn" type="button" class="btn btn-sm btn-danger">
                        <span class="btn-label">{{ __('messages.openwa_restart') }}</span>
                    </button>
                </div>
            </div>
            <div id="testSendResult" class="text-sm mt-2 hidden"></div>
        </div>

        @if (!$isReachable)
            <div class="text-sm text-danger-600 dark:text-danger-400 bg-danger-50 dark:bg-danger-900/20 rounded-[1rem] p-3.5 mb-3">
                {{ __('messages.openwa_unreachable') }}
            </div>

            @if (!empty($managerStatus['last_error']))
                <div id="openwaDiagnostics" class="text-xs bg-ink-50 dark:bg-ink-800/50 rounded-[1rem] p-3.5 mb-3 font-mono whitespace-pre-wrap break-words">
                    <div class="font-semibold text-ink-700 dark:text-ink-200 mb-1">{{ __('messages.openwa_diag') }}</div>
                    <div id="openwaLastError" class="text-ink-600 dark:text-ink-300">{{ $managerStatus['last_error'] }}</div>
                    <div class="mt-2 border-t border-ink-200 dark:border-ink-700 pt-2">
                        <div>{{ __('messages.openwa_diag_app_dir') }}: {{ $managerStatus['resolved_app_dir'] ?? '—' }}</div>
                        <div>{{ __('messages.openwa_diag_node') }}: {{ $managerStatus['resolved_node_binary'] ?? '—' }}</div>
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
                $discCls = 'bg-accent-50 dark:bg-accent-900/20 text-accent-700 dark:text-accent-400';
                $discText = __('messages.openwa_disc_408');
            }
        @endphp
        <div id="disconnectNote" class="text-sm rounded-[1rem] p-3.5 mb-3 {{ $discCls }}" {{ $discReason === null ? 'hidden' : '' }}>{{ $discText }}</div>

        {{-- QR + pairing code: generated together so the user can scan the QR
             OR type the code — whichever they reach first. Stacks on phones,
             sits side by side on wide screens. --}}
        <div class="grid md:grid-cols-2 gap-3">

            {{-- QR code card --}}
            <div id="qrContainer" class="card p-5 flex flex-col items-center justify-center">
                @if ($qr)
                    <img id="qrImage" src="{{ $qr }}" alt="WhatsApp QR"
                         class="w-60 h-60 rounded-2xl border border-ink-200 dark:border-ink-700 bg-white p-2.5 shadow-card">
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-4 text-center leading-relaxed max-w-xs">
                        {{ __('messages.openwa_scan_hint') }}
                    </p>
                @else
                    <div id="qrPlaceholder" class="w-60 h-60 rounded-2xl border border-dashed border-ink-300 dark:border-ink-600 flex items-center justify-center text-center text-sm text-ink-500 dark:text-ink-400 p-4">
                        @if (in_array($status['status'] ?? '', ['connected', 'ready'], true))
                            {{ __('messages.openwa_connected') }}
                        @else
                            {{ __('messages.openwa_qr_unavailable') }}
                        @endif
                    </div>
                @endif
            </div>

            {{-- Pairing code card (alternative to QR) --}}
            <div class="card p-5 flex flex-col items-center justify-center">
                <div class="flex items-center gap-2 mb-1">
                    <x-icon name="device-phone-mobile" class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                    <h4 class="text-sm font-bold text-ink-800 dark:text-ink-100">
                        {{ __('messages.openwa_pairing_title') }}
                    </h4>
                </div>
                <p class="text-xs text-ink-500 dark:text-ink-400 mb-4 text-center leading-relaxed max-w-xs">
                    {{ __('messages.openwa_pairing_hint') }}
                </p>

                <div class="flex items-center gap-2 w-full max-w-xs">
                    <input id="pairingPhone" type="text" inputmode="numeric" placeholder="93700268836"
                           class="form-input flex-1" dir="ltr">
                    <button id="pairingBtn" type="button" class="btn btn-primary btn-sm whitespace-nowrap">
                        {{ __('messages.openwa_generate_code') }}
                    </button>
                </div>

                <div id="pairingResult" class="mt-4 w-full max-w-xs text-center hidden">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">{{ __('messages.openwa_pairing_code_label') }}</div>
                    <div id="pairingCode" class="text-3xl font-extrabold tracking-[0.3em] text-primary-600 dark:text-primary-400 my-2 py-2.5 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800/40 tabular-nums" dir="ltr">--------</div>
                    <div id="pairingExpiry" class="text-xs font-medium text-ink-500 dark:text-ink-400 hidden"></div>
                    <div class="text-[11px] text-ink-400 dark:text-ink-500 mt-2 leading-relaxed">{{ __('messages.openwa_pairing_validity') }}</div>
                </div>
                <div id="pairingEmptyHint" class="mt-4 text-center text-[11px] text-ink-400 dark:text-ink-500 max-w-xs leading-relaxed">
                    {{ __('messages.openwa_pairing_validity') }}
                </div>
            </div>
        </div>
    @endif
</div>

@if ($configured)
@push('scripts')
<script>
    const QR_PLACEHOLDER_CLASS = 'w-60 h-60 rounded-2xl border border-dashed border-ink-300 dark:border-ink-600 flex items-center justify-center text-center text-sm text-ink-500 dark:text-ink-400 p-4';
    const QR_IMAGE_CLASS = 'w-60 h-60 rounded-2xl border border-ink-200 dark:border-ink-700 bg-white p-2.5 shadow-card';

    // Localized session-status labels (mirrors the PHP match above).
    const STATUS_LABELS = {
        connected: '{{ __('messages.openwa_status_connected') }}',
        ready: '{{ __('messages.openwa_status_ready') }}',
        qr_ready: '{{ __('messages.openwa_status_qr_ready') }}',
    };
    const LABEL_UNKNOWN = '{{ __('messages.openwa_status_unknown') }}';
    const LABEL_UNREACHABLE = '{{ __('messages.openwa_status_unreachable') }}';

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
        const label = STATUS_LABELS[status] || (status ? LABEL_UNKNOWN : LABEL_UNREACHABLE);
        const cls = ['connected', 'ready'].includes(status)
            ? 'badge-success'
            : status === 'qr_ready'
                ? 'badge-warning'
                : 'badge-danger';
        badge.textContent = label;
        badge.className = 'badge flex-shrink-0 ' + cls;
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
                    newImg.className = QR_IMAGE_CLASS;
                    const container = document.getElementById('qrContainer');
                    if (placeholder) placeholder.replaceWith(newImg);
                    else if (container) container.insertBefore(newImg, container.firstChild);
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
                    ? '{{ __('messages.openwa_connected') }}'
                    : '{{ __('messages.openwa_qr_unavailable') }}';
                img.replaceWith(div);
            } else {
                showPlaceholder(['connected', 'ready'].includes(data.status)
                    ? '{{ __('messages.openwa_connected') }}'
                    : '{{ __('messages.openwa_qr_unavailable') }}');
            }
        } catch (e) {
            console.error(e);
        } finally {
            refreshing = false;
        }
    }

    document.getElementById('refreshBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('refreshBtn');
        const labelEl = btn.querySelector('.btn-label') || btn;
        const label = labelEl.textContent;
        btn.disabled = true;
        labelEl.textContent = '…';
        await refreshState();
        btn.disabled = false;
        labelEl.textContent = label;
    });

    // The gateway rotates the QR every ~20s. Auto-poll at 10s so the
    // on-screen code is never stale when the user raises the camera —
    // scanning an expired code silently fails ("Couldn't link device").
    setInterval(refreshState, 10000);

    document.getElementById('pairingBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('pairingBtn');
        const phone = document.getElementById('pairingPhone').value.trim();

        if (!phone) {
            showToast('error', '{{ __('messages.openwa_enter_phone') }}');
            return;
        }

        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = '…';

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
                document.getElementById('pairingEmptyHint')?.classList.add('hidden');
                document.getElementById('pairingResult').classList.remove('hidden');
                document.getElementById('pairingCode').textContent = data.pairingCode;
                startPairingCountdown(data.expiresAt);
                startPairingPoll();
            } else {
                showToast('error', data.error || '{{ __('messages.openwa_pairing_error') }}');
            }
        } catch (e) {
            console.error(e);
            showToast('error', '{{ __('messages.openwa_pairing_error') }}');
        } finally {
            btn.disabled = false;
            btn.textContent = label;
        }
    });

    document.getElementById('restartBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('restartBtn');
        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = '{{ __('messages.openwa_restarting') }}';

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
                    ? '{{ __('messages.openwa_restarted') }}'
                    : (data.error || '{{ __('messages.openwa_restart_failed') }}'));
        } catch (e) {
            console.error(e);
            showToast('error', '{{ __('messages.openwa_restart_failed') }}');
        } finally {
            btn.disabled = false;
            btn.textContent = label;
            await refreshState();
        }
    });

    document.getElementById('testSendBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('testSendBtn');
        const result = document.getElementById('testSendResult');
        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = '…';

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
                result.textContent = '{{ __('messages.openwa_test_sent') }}';
            } else {
                result.className = 'text-sm mt-2 text-danger-600 dark:text-danger-400';
                result.textContent = '{{ __('messages.openwa_test_failed') }} ' + (data.error || '');
            }
        } catch (e) {
            console.error(e);
            result.classList.remove('hidden');
            result.className = 'text-sm mt-2 text-danger-600 dark:text-danger-400';
            result.textContent = '{{ __('messages.openwa_test_failed') }} request error';
        } finally {
            btn.disabled = false;
            btn.textContent = label;
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
        let text = '{{ __('messages.openwa_disc_generic') }}';
        if (reason === 401) {
            cls = 'bg-danger-50 dark:bg-danger-900/20 text-danger-600 dark:text-danger-400';
            text = '{{ __('messages.openwa_disc_401') }}';
        } else if (reason === 408) {
            cls = 'bg-accent-50 dark:bg-accent-900/20 text-accent-700 dark:text-accent-400';
            text = '{{ __('messages.openwa_disc_408') }}';
        }
        el.className = 'text-sm rounded-[1rem] p-3.5 mb-3 ' + cls;
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
                ? '{{ __('messages.openwa_pairing_expires_in') }} ' + secs + 's'
                : '{{ __('messages.openwa_pairing_expired') }}';
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
            expiryEl.textContent = '{{ __('messages.openwa_pairing_generating') }}';
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
                document.getElementById('pairingEmptyHint')?.classList.add('hidden');
                document.getElementById('pairingResult').classList.remove('hidden');
                codeEl.textContent = data.pairingCode;
                startPairingCountdown(data.expiresAt);
                startPairingPoll();
            } else {
                // No code returned — let the auto-retry interval try again.
                autoPairingRequested = false;
                if (!silent) showToast('error', data.error || '{{ __('messages.openwa_pairing_error') }}');
            }
        } catch (e) {
            console.error(e);
            // Allow the auto-request interval to retry a failed generation.
            autoPairingRequested = false;
            if (!silent) showToast('error', '{{ __('messages.openwa_pairing_error') }}');
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
