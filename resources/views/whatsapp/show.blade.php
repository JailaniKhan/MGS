@extends('layouts.app')

@section('content')
@php
    $name = $contact?->name ?? __('messages.deleted');
    $initials = mb_strtoupper(mb_substr($name, 0, 1));
    $colors = ['bg-primary-500', 'bg-secondary-500', 'bg-accent-500'];
    $avatarClass = $colors[crc32($type . ':' . $id) % count($colors)];
    $profileRoute = $contact !== null && Route::has($type . 's.show')
        ? route($type . 's.show', $id)
        : null;
    $dateLabel = fn ($time) => $time->isToday()
        ? __('messages.today')
        : ($time->isYesterday() ? __('messages.yesterday') : $time->format('d/m/Y'));
    $sendUrl = $type === 'customer' ? route('customers.message', $id) : route('suppliers.message', $id);
@endphp

<div id="wa-chat"
     data-send-url="{{ $sendUrl }}"
     data-mic-unsupported="{{ __('messages.wa_mic_unsupported') }}"
     data-mic-denied="{{ __('messages.wa_mic_denied') }}"
     data-audio-invalid="{{ __('messages.wa_audio_invalid') }}"
     data-audio-too-large="{{ __('messages.wa_audio_too_large') }}"
     data-failed-label="{{ __('messages.wa_message_failed') }}"
     data-voice-label="{{ __('messages.wa_voice_note') }}"
     class="flex flex-col -mx-4">

    {{-- Conversation header --}}
    <div class="flex items-center gap-2.5 px-4 py-2 bg-white/90 dark:bg-[#18191a]/90 backdrop-blur-xl border-b border-ink-100 dark:border-white/[0.05] sticky top-14 z-20">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white {{ $avatarClass }} flex-shrink-0 ring-2 ring-white/60 dark:ring-white/10">
            {{ $initials }}
        </div>
        <div class="min-w-0 flex-1 leading-tight">
            <h2 class="text-sm font-bold text-ink-900 dark:text-white truncate">{{ $name }}</h2>
            <p class="text-[10px] text-ink-500 dark:text-ink-400 truncate flex items-center gap-1" dir="ltr">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-brand"></span>
                {{ $contact?->phone ?? __('messages.wa_channel') }}
            </p>
        </div>
        @if ($profileRoute)
            <x-icon-button name="user" href="{{ $profileRoute }}" label="{{ $type === 'customer' ? __('messages.customer') : __('messages.supplier') }}"/>
        @endif
        <x-back-button href="{{ route('whatsapp.chats.index') }}"/>
    </div>

    {{-- Message canvas --}}
    <div class="wa-canvas flex-1 px-3 pt-3 pb-44 space-y-2 min-h-[55vh]" id="wa-messages">
        @forelse ($messages as $reminder)
            @include('whatsapp.partials.bubble', ['reminder' => $reminder, 'dateLabel' => $dateLabel])
        @empty
            <div class="flex flex-col items-center justify-center text-center pt-14 gap-3 page-enter">
                <div class="w-16 h-16 rounded-3xl bg-white/70 dark:bg-white/[0.04] border border-ink-100/70 dark:border-white/[0.06] shadow-card flex items-center justify-center">
                    <x-icon name="chat-bubble-left-right" class="w-7 h-7 text-brand" strokeWidth="1.5"/>
                </div>
                <div class="max-w-[16rem]">
                    <p class="text-sm font-bold text-ink-700 dark:text-ink-200">{{ __('messages.wa_empty_conversation') }}</p>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-1 leading-relaxed">{{ __('messages.wa_empty_conversation_hint') }}</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Compose bar --}}
    <div class="fixed inset-x-3 z-30 compose-bar-offset" id="wa-compose-root">
        <div class="max-w-[34rem] mx-auto">

            {{-- Inline hint toast --}}
            <div id="wa-hint" class="hidden mb-2 mx-auto w-fit max-w-full px-3.5 py-2 rounded-xl bg-ink-900/90 dark:bg-white/90 text-white dark:text-ink-900 text-[11px] font-medium shadow-card text-center"></div>

            {{-- Voice preview card --}}
            <div id="wa-preview" class="hidden mb-2 bg-white dark:bg-[#1e2127] border border-ink-100 dark:border-white/[0.06] rounded-2xl shadow-card p-2.5 flex items-center gap-2.5 page-enter">
                <span class="text-[9px] font-bold uppercase tracking-wider text-ink-400 flex-shrink-0">{{ __('messages.wa_preview_voice') }}</span>
                <div class="flex-1 min-w-0" id="wa-preview-slot"></div>
                <button type="button" id="wa-preview-cancel" class="w-9 h-9 rounded-xl grid place-items-center text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-900/30 transition-colors flex-shrink-0" aria-label="{{ __('messages.wa_cancel_recording') }}">
                    <x-icon name="trash" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                </button>
                <button type="button" id="wa-preview-send" class="w-10 h-10 rounded-xl bg-brand text-white grid place-items-center hover:brightness-105 active:scale-95 transition-all flex-shrink-0 shadow-sm" aria-label="{{ __('messages.wa_send') }}">
                    <x-icon name="paper-airplane" class="w-4.5 h-4.5 rtl:-scale-x-100" strokeWidth="1.8"/>
                </button>
            </div>

            {{-- Main bar --}}
            <div id="wa-bar" class="bg-white/95 dark:bg-[#1e2127]/95 backdrop-blur-xl border border-ink-100 dark:border-white/[0.07] rounded-[1.35rem] shadow-card flex items-end gap-1 p-1.5">

                {{-- Idle / typing --}}
                <div id="wa-compose-idle" class="flex items-end gap-1 flex-1 min-w-0">
                    <input type="file" id="wa-file" accept="audio/*,.webm,.ogg,.m4a,.aac,.mp3,.wav" class="hidden">
                    <button type="button" id="wa-attach" class="w-10 h-10 rounded-full grid place-items-center text-ink-500 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-white/[0.06] active:scale-95 transition-all flex-shrink-0" aria-label="{{ __('messages.wa_attach_audio') }}">
                        <x-icon name="paper-clip" class="w-5 h-5" strokeWidth="1.8"/>
                    </button>
                    <input type="text" id="wa-input" autocomplete="off" maxlength="4096"
                           placeholder="{{ __('messages.wa_type_message') }}"
                           class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 focus:outline-none text-sm text-ink-800 dark:text-ink-100 placeholder:text-ink-400 dark:placeholder:text-ink-500 py-2.5 px-1">
                    <button type="button" id="wa-mic" class="w-10 h-10 rounded-full bg-brand text-white grid place-items-center shadow-sm hover:brightness-105 active:scale-95 transition-all flex-shrink-0" aria-label="{{ __('messages.wa_record_hint') }}">
                        <x-icon name="microphone" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                    </button>
                    <button type="button" id="wa-send-text" class="hidden w-10 h-10 rounded-full bg-brand text-white grid place-items-center shadow-sm hover:brightness-105 active:scale-95 transition-all flex-shrink-0 disabled:opacity-50" aria-label="{{ __('messages.wa_send') }}">
                        <x-icon name="paper-airplane" class="w-4.5 h-4.5 rtl:-scale-x-100" strokeWidth="1.8"/>
                    </button>
                </div>

                {{-- Recording --}}
                <div id="wa-compose-recording" class="hidden flex-1 items-center gap-2 px-1.5" style="display:none;">
                    <span class="relative flex w-3 h-3 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-danger-400 opacity-60"></span>
                        <span class="relative inline-flex rounded-full w-3 h-3 bg-danger-500"></span>
                    </span>
                    <span class="text-sm font-bold tabular-nums text-danger-600 dark:text-danger-400" dir="ltr" id="wa-rec-timer">00:00</span>
                    <span class="text-[11px] text-ink-400 truncate flex-1">{{ __('messages.wa_recording') }}</span>
                    <button type="button" id="wa-rec-cancel" class="w-10 h-10 rounded-full grid place-items-center text-ink-500 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-white/[0.06] active:scale-95 transition-all" aria-label="{{ __('messages.wa_cancel_recording') }}">
                        <x-icon name="trash" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                    </button>
                    <button type="button" id="wa-rec-stop" class="w-10 h-10 rounded-full bg-ink-900 dark:bg-white text-white dark:text-ink-900 grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('messages.wa_preview_voice') }}">
                        <x-icon name="stop" class="w-4 h-4"/>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('wa-chat');
    if (!root) return;

    var cfg = {
        sendUrl: root.dataset.sendUrl,
        micUnsupported: root.dataset.micUnsupported,
        micDenied: root.dataset.micDenied,
        audioInvalid: root.dataset.audioInvalid,
        audioTooLarge: root.dataset.audioTooLarge,
        failedLabel: root.dataset.failedLabel,
        voiceLabel: root.dataset.voiceLabel,
    };

    // Base64 inflates audio ~33%; PHP's post_max_size (8M by default)
    // silently discards oversized bodies, so cap payloads client-side.
    var MAX_AUDIO_PAYLOAD = 6 * 1024 * 1024;

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var canvas = document.getElementById('wa-messages');
    var input = document.getElementById('wa-input');
    var idleBox = document.getElementById('wa-compose-idle');
    var recBox = document.getElementById('wa-compose-recording');
    var micBtn = document.getElementById('wa-mic');
    var sendBtn = document.getElementById('wa-send-text');
    var attachBtn = document.getElementById('wa-attach');
    var fileInput = document.getElementById('wa-file');
    var hint = document.getElementById('wa-hint');
    var previewCard = document.getElementById('wa-preview');
    var previewSlot = document.getElementById('wa-preview-slot');

    var recording = null;   // MediaRecorder
    var recStream = null;
    var recTimer = null;
    var recSeconds = 0;
    var pendingBlob = null; // Blob awaiting preview->send

    var micSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && typeof MediaRecorder !== 'undefined');
    if (!micSupported && micBtn) {
        micBtn.classList.add('opacity-50');
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function showHint(msg) {
        hint.textContent = msg;
        hint.classList.remove('hidden');
        clearTimeout(showHint._t);
        showHint._t = setTimeout(function () { hint.classList.add('hidden'); }, 3200);
    }

    function scrollToBottom() {
        requestAnimationFrame(function () { window.scrollTo(0, document.body.scrollHeight); });
    }
    scrollToBottom();

    // ---------- bubble factory ----------
    function buildBubble(opts) {
        // opts: {kind:'text'|'voice', text?, mediaUrl?, status:'sent'|'failed', timeLabel}
        var wrap = document.createElement('div');
        wrap.className = 'flex justify-end';

        var bubble = document.createElement('div');
        bubble.className = 'max-w-[82%] rounded-2xl rounded-ee-lg px-3 py-2 text-sm shadow-sm break-words ' +
            (opts.status === 'failed'
                ? 'bg-danger-100 dark:bg-danger-900/50 text-danger-800 dark:text-danger-200'
                : 'bg-[#d9fdd3] dark:bg-primary-900/50 text-ink-900 dark:text-ink-100');

        if (opts.kind === 'voice' && opts.mediaUrl) {
            bubble.insertAdjacentHTML('beforeend',
                '<div class="flex items-center gap-2 py-0.5">' +
                '<button type="button" class="wa-play w-9 h-9 rounded-full bg-brand text-white grid place-items-center active:scale-95 transition-transform flex-shrink-0" data-src="' + esc(opts.mediaUrl) + '" aria-label="' + esc(cfg.voiceLabel || 'Voice note') + '">' +
                '<svg class="wa-play-icon w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5.14v13.72c0 .96 1.05 1.55 1.87 1.05l10.29-6.86a1.25 1.25 0 000-2.1L9.87 4.09C9.05 3.59 8 4.18 8 5.14z"/></svg>' +
                '</button>' +
                '<div class="flex items-end gap-[3px] h-6" aria-hidden="true">' +
                [10, 18, 12, 22, 15, 20, 11].map(function (h, i) {
                    return '<span class="wa-eq w-[3px] rounded-full bg-black/25 dark:bg-white/25" style="height:' + h + 'px;animation-delay:' + (i * 120) + 'ms"></span>';
                }).join('') +
                '</div>' +
                '<span class="text-[10px] opacity-70 tabular-nums wa-duration" dir="ltr">--:--</span>' +
                '</div>');
        } else {
            var div = document.createElement('div');
            div.className = 'leading-snug whitespace-pre-line';
            div.textContent = opts.text || '';
            bubble.appendChild(div);
        }

        var meta = document.createElement('div');
        meta.className = 'flex items-center justify-end gap-1 mt-1';
        meta.innerHTML =
            '<span class="text-[10px] opacity-70 tabular-nums" dir="ltr">' + esc(opts.timeLabel) + '</span>' +
            '<svg class="w-3.5 h-3.5 ' + (opts.status === 'sent' ? 'text-primary-600 dark:text-primary-300' : 'text-danger-500') + '" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            (opts.status === 'sent'
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>') +
            '</svg>';
        bubble.appendChild(meta);

        if (opts.status === 'failed' && opts.kind !== 'voice') {
            bubble.insertAdjacentHTML('beforeend', '<div class="text-[10px] mt-1 opacity-80">' + esc(cfg.failedLabel) + '</div>');
        }

        wrap.appendChild(bubble);
        wirePlayer(wrap);
        return wrap;
    }

    // ---------- voice player ----------
    function fmtTime(sec) {
        sec = Math.max(0, Math.round(sec));
        return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
    }

    function wirePlayer(scope) {
        scope.querySelectorAll('.wa-play').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.dataset.src;
                var audio = btn._audio || (btn._audio = new Audio(src));

                document.querySelectorAll('.wa-play').forEach(function (other) {
                    if (other !== btn && other._audio) { other._audio.pause(); setPlayState(other, false); }
                });

                if (!btn._wired) {
                    btn._wired = true;
                    var durEl = btn.closest('.flex').querySelector('.wa-duration');
                    audio.addEventListener('loadedmetadata', function () {
                        if (isFinite(audio.duration)) durEl.textContent = fmtTime(audio.duration);
                    });
                    audio.addEventListener('ended', function () { setPlayState(btn, false); });
                }

                if (audio.paused) {
                    audio.play().catch(function () {});
                    setPlayState(btn, true);
                } else {
                    audio.pause();
                    setPlayState(btn, false);
                }
            });
        });
    }

    function setPlayState(btn, playing) {
        var icon = btn.querySelector('.wa-play-icon');
        if (icon) {
            icon.innerHTML = playing
                ? '<path d="M7 5h4v14H7zM13 5h4v14h-4z"/>'
                : '<path d="M8 5.14v13.72c0 .96 1.05 1.55 1.87 1.05l10.29-6.86a1.25 1.25 0 000-2.1L9.87 4.09C9.05 3.59 8 4.18 8 5.14z"/>';
        }
        btn.closest('.flex').querySelectorAll('.wa-eq').forEach(function (bar) {
            bar.style.animation = playing ? 'waEq 1s ease-in-out infinite' : '';
        });
    }

    wirePlayer(canvas);

    // ---------- sending ----------
    var busy = false;

    function post(payload) {
        busy = true;
        sendBtn.disabled = true;
        payload._token = csrf;
        return fetch(cfg.sendUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false }; });
        }).finally(function () {
            busy = false;
            sendBtn.disabled = false;
        });
    }

    function deliver(kind, payload, displayText) {
        post(payload).then(function (res) {
            var ok = res && res.ok;
            canvas.insertAdjacentElement('beforeend', buildBubble({
                kind: kind,
                text: kind === 'text' ? displayText : null,
                mediaUrl: ok ? res.media_url : null,
                status: ok ? 'sent' : 'failed',
                timeLabel: (res && res.time_label) || fmtTime(Math.floor(Date.now() / 1000) % 86400),
            }));
            if (!ok) showHint(cfg.failedLabel);
            scrollToBottom();
        });
    }

    sendBtn.addEventListener('click', sendText);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendText(); }
    });
    input.addEventListener('input', function () {
        var has = input.value.trim().length > 0;
        sendBtn.classList.toggle('hidden', !has);
        micBtn.classList.toggle('hidden', has);
    });

    function sendText() {
        var text = input.value.trim();
        if (!text || busy) return;
        input.value = '';
        input.dispatchEvent(new Event('input'));
        deliver('text', { type: 'text', message: text }, text);
    }

    // ---------- attachments ----------
    attachBtn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        var f = fileInput.files[0];
        fileInput.value = '';
        if (!f) return;
        if (!(f.type.indexOf('audio/') === 0 || /\.(webm|ogg|oga|opus|m4a|mp4|aac|mp3|wav|amr)$/i.test(f.name))) {
            showHint(cfg.audioInvalid);
            return;
        }
        blobToDataUrl(f).then(function (dataUrl) { openPreview(dataUrl, f.type, Math.round(f.size / 1024)); });
    });

    function blobToDataUrl(blob) {
        return new Promise(function (resolve, reject) {
            var fr = new FileReader();
            fr.onload = function () { resolve(fr.result); };
            fr.onerror = reject;
            fr.readAsDataURL(blob);
        });
    }

    // ---------- preview ----------
    // Always stores a base64 data URL: the backend expects a string, and a
    // raw Blob would JSON.stringify to "{}" and fail validation silently.
    function openPreview(dataUrl, mime) {
        if (dataUrl.length > MAX_AUDIO_PAYLOAD) {
            showHint(cfg.audioTooLarge);
            return;
        }
        pendingBlob = { dataUrl: dataUrl, mime: mime || 'audio/webm' };
        previewSlot.innerHTML =
            '<audio src="' + dataUrl + '" controls class="w-full h-9"></audio>';
        previewCard.classList.remove('hidden');
        idleBox.style.display = 'none';
        scrollToBottom();
    }

    function closePreview() {
        pendingBlob = null;
        previewCard.classList.add('hidden');
        previewSlot.innerHTML = '';
        idleBox.style.display = '';
    }

    document.getElementById('wa-preview-cancel').addEventListener('click', closePreview);

    document.getElementById('wa-preview-send').addEventListener('click', function () {
        if (!pendingBlob || busy) return;
        var payload = { type: 'voice', audio: pendingBlob.dataUrl, mimetype: pendingBlob.mime };
        closePreview();
        deliver('voice', payload, '');
    });

    // ---------- recording ----------
    micBtn.addEventListener('click', function () {
        if (busy) return;
        if (!micSupported) { showHint(cfg.micUnsupported); return; }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            recStream = stream;
            // 32 kbps Opus is plenty for voice notes and keeps the base64
            // payload far below PHP's post_max_size. Fall back to the browser
            // default if it rejects the bitrate hint.
            try {
                recording = new MediaRecorder(stream, { audioBitsPerSecond: 32000 });
            } catch (e) {
                recording = new MediaRecorder(stream);
            }
            var chunks = [];
            recording.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
            recording.onstop = function () {
                stopTimerUi();
                recStream.getTracks().forEach(function (t) { t.stop(); });
                var blob = new Blob(chunks, { type: recording.mimeType || 'audio/webm' });
                // Convert to a base64 data URL BEFORE preview — the send
                // payload must be a string (a raw Blob stringifies to "{}").
                if (blob.size > 0) {
                    blobToDataUrl(blob).then(function (dataUrl) {
                        openPreview(dataUrl, blob.type);
                    });
                }
            };
            recording.start();
            startTimerUi();
        }).catch(function () { showHint(cfg.micDenied); });
    });

    function startTimerUi() {
        idleBox.style.display = 'none';
        recBox.classList.remove('hidden');
        recBox.style.display = 'flex';
        recSeconds = 0;
        document.getElementById('wa-rec-timer').textContent = '00:00';
        recTimer = setInterval(function () {
            recSeconds++;
            document.getElementById('wa-rec-timer').textContent = fmtTime(recSeconds);
        }, 1000);
    }

    function stopTimerUi() {
        clearInterval(recTimer);
        recBox.classList.add('hidden');
        recBox.style.display = 'none';
        idleBox.style.display = '';
    }

    document.getElementById('wa-rec-cancel').addEventListener('click', function () {
        if (recording && recording.state !== 'inactive') {
            recording.onstop = function () {
                stopTimerUi();
                recStream.getTracks().forEach(function (t) { t.stop(); });
            };
            recording.stop();
            recording = null;
        }
    });

    document.getElementById('wa-rec-stop').addEventListener('click', function () {
        if (recording && recording.state !== 'inactive') recording.stop();
    });
})();
</script>

<style>
    /* Chat canvas texture: faint radial dots over WhatsApp's warm paper tone */
    .wa-canvas {
        background-color: #efeae2;
        background-image: radial-gradient(rgb(0 0 0 / 0.028) 1px, transparent 1.2px);
        background-size: 22px 22px;
    }
    .dark .wa-canvas {
        background-color: rgb(11 12 14 / 0.65);
        background-image: radial-gradient(rgb(255 255 255 / 0.03) 1px, transparent 1.2px);
    }
    .compose-bar-offset {
        bottom: calc(5.6rem + env(safe-area-inset-bottom, 0px));
    }
    body.keyboard-visible .compose-bar-offset {
        bottom: calc(0.75rem + env(keyboard-inset-height, 0px));
    }
    @keyframes waEq {
        0%, 100% { transform: scaleY(0.45); }
        50% { transform: scaleY(1); }
    }
    .wa-eq { transform-origin: bottom; }
</style>
@endsection
