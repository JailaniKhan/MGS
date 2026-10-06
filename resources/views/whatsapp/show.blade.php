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
     data-poll-url="{{ route('whatsapp.chats.messages', ['type' => $type, 'id' => $id]) }}"
     data-poll-interval="5000"
     data-mic-unsupported="{{ __('messages.wa_mic_unsupported') }}"
     data-mic-denied="{{ __('messages.wa_mic_denied') }}"
     data-audio-invalid="{{ __('messages.wa_audio_invalid') }}"
     data-audio-too-large="{{ __('messages.wa_audio_too_large') }}"
     data-image-invalid="{{ __('messages.wa_image_invalid') }}"
     data-image-too-large="{{ __('messages.wa_image_too_large') }}"
     data-photo-label="{{ __('messages.wa_photo') }}"
     data-photo-caption="{{ __('messages.wa_photo_caption') }}"
     data-failed-label="{{ __('messages.wa_message_failed') }}"
     data-voice-label="{{ __('messages.wa_voice_note') }}"
     class="relative flex flex-col h-[calc(100dvh-3.5rem)]">

    {{-- Conversation header --}}
    <div class="relative flex items-center gap-3 px-3 py-2.5 bg-white/95 dark:bg-[#14161a]/95 backdrop-blur-xl border-b border-ink-100 dark:border-white/[0.06] sticky wa-header-stick z-20">
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-brand/30 dark:via-brand/40 to-transparent" aria-hidden="true"></div>

        <x-back-button href="{{ route('whatsapp.chats.index') }}"/>

        <div class="wa-avatar relative overflow-hidden w-11 h-11 rounded-[0.95rem] flex items-center justify-center text-sm font-bold text-white {{ $avatarClass }} flex-shrink-0 ring-1 ring-black/[0.06] dark:ring-white/[0.1]">
            {{ $initials }}
        </div>

        <div class="min-w-0 flex-1 leading-tight">
            <h2 class="text-[15px] font-extrabold tracking-tight text-ink-900 dark:text-white truncate">{{ $name }}</h2>
            <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate flex items-center gap-1.5" dir="ltr">
                <span class="relative inline-flex w-1.5 h-1.5 flex-shrink-0">
                    <span class="absolute inline-flex w-full h-full rounded-full bg-brand opacity-60 animate-ping"></span>
                    <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-brand"></span>
                </span>
                {{ $contact?->phone ?? __('messages.wa_channel') }}
            </p>
        </div>

        @if ($profileRoute)
            <x-icon-button name="user" href="{{ $profileRoute }}" label="{{ $type === 'customer' ? __('messages.customer') : __('messages.supplier') }}"/>
        @endif
    </div>

    {{-- Message canvas --}}
    <div class="wa-canvas flex-1 overflow-y-auto px-2 pt-4 pb-2 space-y-2 min-h-0" id="wa-messages" data-last-id="{{ $messages->last()->id ?? 0 }}">
        @forelse ($messages as $reminder)
            @include('whatsapp.partials.bubble', ['reminder' => $reminder, 'dateLabel' => $dateLabel])
        @empty
            <div class="flex flex-col items-center justify-center text-center h-full min-h-[50vh] gap-4 page-enter">
                <div class="absolute top-16 start-1/2 -translate-x-1/2 w-72 h-72 rounded-full bg-brand/[0.08] dark:bg-brand/[0.05] blur-3xl" aria-hidden="true"></div>
                <div class="relative w-20 h-20 rounded-[1.65rem] brand-grad text-white flex items-center justify-center shadow-fab -rotate-3">
                    <x-icon name="chat-bubble-left-right" class="w-8 h-8" strokeWidth="1.5"/>
                </div>
                <div class="relative max-w-[18rem]">
                    <p class="text-sm font-bold text-ink-700 dark:text-ink-200">{{ __('messages.wa_empty_conversation') }}</p>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-1.5 leading-relaxed">{{ __('messages.wa_empty_conversation_hint') }}</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Compose bar --}}
    <div class="sticky bottom-0 z-30 px-2 pb-2 pt-1 bg-gradient-to-t from-ink-50 via-ink-50/95 to-transparent dark:from-[#0b0c0e] dark:via-[#0b0c0e]/95" id="wa-compose-root">
        <div class="wa-compose-inner">

            {{-- Inline hint toast --}}
            <div id="wa-hint" class="hidden mb-2 mx-auto w-fit max-w-full px-3.5 py-2 rounded-xl bg-ink-900/90 dark:bg-white/90 text-white dark:text-ink-900 text-[11px] font-medium shadow-lg text-center"></div>

            {{-- Media preview card (voice or photo) --}}
            <div id="wa-preview" class="hidden mb-2 bg-white/95 dark:bg-[#1e2127]/95 backdrop-blur-xl border border-ink-100 dark:border-white/[0.07] rounded-2xl shadow-lg p-2.5 flex items-center gap-2.5 page-enter">
                <span class="text-[9px] font-bold uppercase tracking-wider text-ink-400 flex-shrink-0" id="wa-preview-label">{{ __('messages.wa_preview_voice') }}</span>
                <div class="flex-1 min-w-0" id="wa-preview-slot"></div>
                <button type="button" id="wa-preview-cancel" class="w-9 h-9 rounded-xl grid place-items-center text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-900/30 transition-colors flex-shrink-0" aria-label="{{ __('messages.wa_cancel_recording') }}">
                    <x-icon name="trash" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                </button>
                <button type="button" id="wa-preview-send" class="w-10 h-10 rounded-xl brand-grad text-white grid place-items-center hover:scale-105 active:scale-95 transition-all flex-shrink-0 shadow-btn" aria-label="{{ __('messages.wa_send') }}">
                    <x-icon name="paper-airplane" class="w-4.5 h-4.5 rtl:-scale-x-100" strokeWidth="1.8"/>
                </button>
            </div>

            {{-- Main bar --}}
            <div id="wa-bar" class="bg-white/95 dark:bg-[#1e2127]/95 backdrop-blur-xl border border-ink-100 dark:border-white/[0.07] rounded-2xl shadow-lg flex items-end gap-1 p-1.5">

                {{-- Idle / typing --}}
                <div id="wa-compose-idle" class="flex items-end gap-1 flex-1 min-w-0">
                    <input type="file" id="wa-file" accept="audio/*,image/*,.webm,.ogg,.m4a,.aac,.mp3,.wav,.jpg,.jpeg,.png,.webp,.gif" class="hidden">
                    <button type="button" id="wa-attach" class="w-10 h-10 rounded-full grid place-items-center text-ink-500 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-white/[0.06] active:scale-95 transition-all flex-shrink-0" aria-label="{{ __('messages.wa_attach_media') }}">
                        <x-icon name="paper-clip" class="w-5 h-5" strokeWidth="1.8"/>
                    </button>
                    <input type="text" id="wa-input" autocomplete="off" maxlength="4096"
                           placeholder="{{ __('messages.wa_type_message') }}"
                           class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 focus:outline-none text-sm text-ink-800 dark:text-ink-100 placeholder:text-ink-400 dark:placeholder:text-ink-500 py-2.5 px-1">
                    <button type="button" id="wa-mic" class="w-10 h-10 rounded-full brand-grad text-white grid place-items-center shadow-btn hover:scale-105 active:scale-95 transition-all flex-shrink-0" aria-label="{{ __('messages.wa_record_hint') }}">
                        <x-icon name="microphone" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                    </button>
                    <button type="button" id="wa-send-text" class="hidden w-10 h-10 rounded-full brand-grad text-white grid place-items-center shadow-btn hover:scale-105 active:scale-95 transition-all flex-shrink-0 disabled:opacity-50" aria-label="{{ __('messages.wa_send') }}">
                        <x-icon name="paper-airplane" class="w-4.5 h-4.5 rtl:-scale-x-100" strokeWidth="1.8"/>
                    </button>
                </div>

                {{-- Recording: trash | rec dot + timer | live waveform | stop --}}
                <div id="wa-compose-recording" class="hidden flex-1 items-center gap-1.5 px-1">
                    <button type="button" id="wa-rec-cancel" class="w-10 h-10 rounded-full grid place-items-center text-ink-500 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-white/[0.06] active:scale-95 transition-all flex-shrink-0" aria-label="{{ __('messages.wa_cancel_recording') }}">
                        <x-icon name="trash" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                    </button>
                    <span class="relative flex w-2.5 h-2.5 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-danger-400 opacity-60"></span>
                        <span class="relative inline-flex rounded-full w-2.5 h-2.5 bg-danger-500"></span>
                    </span>
                    <span class="text-[13px] font-bold tabular-nums text-ink-700 dark:text-ink-200 flex-shrink-0" dir="ltr" id="wa-rec-timer">00:00</span>
                    <div class="flex items-center justify-between gap-[2px] h-6 flex-1 min-w-0 px-0.5" id="wa-rec-levels" aria-hidden="true">
                        @for ($i = 0; $i < 14; $i++)
                            <span class="rec-bar w-[3px] h-4 rounded-full bg-brand/50 dark:bg-brand/40" style="transform:scaleY(.3)"></span>
                        @endfor
                    </div>
                    <button type="button" id="wa-rec-stop" class="w-10 h-10 rounded-full brand-grad text-white grid place-items-center shadow-btn hover:scale-105 active:scale-95 transition-all flex-shrink-0" aria-label="{{ __('messages.wa_preview_voice') }}">
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
        imageInvalid: root.dataset.imageInvalid,
        imageTooLarge: root.dataset.imageTooLarge,
        photoLabel: root.dataset.photoLabel,
        photoCaption: root.dataset.photoCaption,
        failedLabel: root.dataset.failedLabel,
        voiceLabel: root.dataset.voiceLabel,
    };

    var MAX_AUDIO_PAYLOAD = 6 * 1024 * 1024;
    var MAX_IMAGE_PAYLOAD = 16 * 1024 * 1024;

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

    var recording = null;
    var recStream = null;
    var recTimer = null;
    var recSeconds = 0;
    var pendingBlob = null;

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
        requestAnimationFrame(function () { canvas.scrollTop = canvas.scrollHeight; });
    }
    scrollToBottom();

    // ---------- bubble factory ----------
    function buildBubble(opts) {
        var wrap = document.createElement('div');
        wrap.className = 'flex justify-end page-enter';

        var bubble = document.createElement('div');
        bubble.className = 'wa-bubble relative max-w-[85%] px-3 py-2 text-sm shadow-sm break-words ' +
            (opts.status === 'failed'
                ? 'bg-danger-100 dark:bg-danger-900/50 text-danger-800 dark:text-danger-200'
                : 'bg-[#d9fdd3] dark:bg-[#005c4b] text-ink-900 dark:text-[#e9edef]');

        bubble.insertAdjacentHTML('afterbegin',
            '<span class="wa-tail absolute -me-2 bottom-1.5 w-2 h-3 ' +
            (opts.status === 'failed' ? 'bg-danger-100 dark:bg-danger-900/50' : 'bg-[#d9fdd3] dark:bg-[#005c4b]') +
            '" aria-hidden="true"></span>');

        if (opts.kind === 'voice' && opts.mediaUrl) {
            bubble.insertAdjacentHTML('beforeend',
                '<div class="flex items-center gap-2 py-0.5">' +
                '<button type="button" class="wa-play wa-play-out w-9 h-9 rounded-full grid place-items-center active:scale-95 transition-transform flex-shrink-0" data-src="' + esc(opts.mediaUrl) + '" aria-label="' + esc(cfg.voiceLabel || 'Voice note') + '">' +
                '<svg class="wa-play-icon w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5.14v13.72c0 .96 1.05 1.55 1.87 1.05l10.29-6.86a1.25 1.25 0 000-2.1L9.87 4.09C9.05 3.59 8 4.18 8 5.14z"/></svg>' +
                '</button>' +
                '<div class="flex items-end gap-[3px] h-5 flex-1" aria-hidden="true">' +
                [10, 18, 12, 22, 15, 20, 11].map(function (h, i) {
                    return '<span class="wa-eq flex-1 max-w-[3px] rounded-full" style="height:' + h + 'px"></span>';
                }).join('') +
                '</div>' +
                '<span class="text-[10px] opacity-60 tabular-nums wa-duration flex-shrink-0" dir="ltr">--:--</span>' +
                '</div>');
        } else if (opts.kind === 'image' && opts.mediaUrl) {
            bubble.classList.add('wa-img-bubble');
            bubble.insertAdjacentHTML('beforeend',
                '<img src="' + esc(opts.mediaUrl) + '" alt="' + esc(cfg.photoLabel || 'Photo') + '" class="wa-img rounded-xl max-w-full">' +
                (opts.text ? '<div class="leading-snug whitespace-pre-line wa-text mt-1.5" dir="auto">' + esc(opts.text) + '</div>' : ''));
        } else {
            var div = document.createElement('div');
            div.className = 'leading-snug whitespace-pre-line wa-text';
            div.setAttribute('dir', 'auto');
            div.textContent = opts.text || '';
            bubble.appendChild(div);
        }

        var meta = document.createElement('div');
        meta.className = 'flex items-center justify-end gap-0.5 mt-1 wa-meta';
        meta.innerHTML =
            '<span class="text-[10px] opacity-60 tabular-nums" dir="ltr">' + esc(opts.timeLabel) + '</span>' +
            '<svg class="w-3.5 h-3.5 ' + (opts.status === 'sent' ? 'text-[#53bdeb]' : 'text-danger-500') + '" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
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
                text: kind === 'voice' ? null : displayText,
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
        if (f.type.indexOf('image/') === 0 || /\.(jpe?g|png|webp|gif)$/i.test(f.name)) {
            if (f.size > MAX_IMAGE_PAYLOAD) { showHint(cfg.imageTooLarge); return; }
            blobToDataUrl(f).then(function (dataUrl) { openPreview(dataUrl, f.type, 'image'); });
        } else if (f.type.indexOf('audio/') === 0 || /\.(webm|ogg|oga|opus|m4a|mp4|aac|mp3|wav|amr)$/i.test(f.name)) {
            if (f.size > MAX_AUDIO_PAYLOAD) { showHint(cfg.audioTooLarge); return; }
            blobToDataUrl(f).then(function (dataUrl) { openPreview(dataUrl, f.type, 'voice'); });
        } else {
            showHint(cfg.audioInvalid);
        }
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
    var previewLabel = document.getElementById('wa-preview-label');

    function openPreview(dataUrl, mime, kind) {
        var limit = kind === 'image' ? MAX_IMAGE_PAYLOAD : MAX_AUDIO_PAYLOAD;
        if (dataUrl.length > limit * 1.4) {
            showHint(kind === 'image' ? cfg.imageTooLarge : cfg.audioTooLarge);
            return;
        }
        pendingBlob = { dataUrl: dataUrl, mime: mime || 'audio/webm', kind: kind, caption: '' };
        if (kind === 'image') {
            previewLabel.textContent = cfg.photoLabel || 'Photo';
            previewSlot.innerHTML =
                '<div class="flex items-center gap-2.5 w-full min-w-0">' +
                '<img src="' + dataUrl + '" alt="" class="w-12 h-12 rounded-xl object-cover flex-shrink-0 ring-1 ring-black/10 dark:ring-white/10">' +
                '<input type="text" id="wa-caption-input" maxlength="4096" autocomplete="off" placeholder="' + esc(cfg.photoCaption || '') + '" ' +
                'class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 focus:outline-none text-sm text-ink-800 dark:text-ink-100 placeholder:text-ink-400 py-1.5 px-1">' +
                '</div>';
            var capInput = document.getElementById('wa-caption-input');
            capInput.addEventListener('input', function () { pendingBlob.caption = capInput.value; });
        } else {
            previewLabel.textContent = root.dataset.voiceLabel || 'Voice note';
            previewSlot.innerHTML =
                '<audio src="' + dataUrl + '" controls class="w-full h-9"></audio>';
        }
        previewCard.classList.remove('hidden');
        idleBox.classList.add('hidden');
        scrollToBottom();
    }

    function closePreview() {
        pendingBlob = null;
        previewCard.classList.add('hidden');
        previewSlot.innerHTML = '';
        idleBox.classList.remove('hidden');
    }

    document.getElementById('wa-preview-cancel').addEventListener('click', closePreview);

    document.getElementById('wa-preview-send').addEventListener('click', function () {
        if (!pendingBlob || busy) return;
        var blob = pendingBlob;
        if (blob.kind === 'image') {
            var payload = { type: 'image', image: blob.dataUrl, message: blob.caption };
            closePreview();
            deliver('image', payload, blob.caption);
        } else {
            var payload = { type: 'voice', audio: blob.dataUrl, mimetype: blob.mime };
            closePreview();
            deliver('voice', payload, '');
        }
    });

    // ---------- recording ----------
    micBtn.addEventListener('click', function () {
        if (busy) return;
        if (!micSupported) { showHint(cfg.micUnsupported); return; }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            recStream = stream;
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
                if (blob.size > 0) {
                    blobToDataUrl(blob).then(function (dataUrl) {
                        openPreview(dataUrl, blob.type);
                    });
                }
            };
            recording.start(1000);
            startTimerUi();
        }).catch(function () { showHint(cfg.micDenied); });
    });

    function startTimerUi() {
        idleBox.classList.add('hidden');
        recBox.classList.remove('hidden');

        var timerEl = document.getElementById('wa-rec-timer');
        var startTs = Date.now();
        timerEl.textContent = '00:00';
        setupLevels();
        clearInterval(recTimer);
        recTimer = setInterval(function () {
            var elapsed = Date.now() - startTs;
            timerEl.textContent = fmtTime(Math.floor(elapsed / 1000));
            if (analyser && freqData) analyser.getByteFrequencyData(freqData);
            paintLevels(elapsed);
        }, 100);
    }

    function stopTimerUi() {
        clearInterval(recTimer);
        if (audioCtx) { try { audioCtx.close(); } catch (e) {} }
        audioCtx = null; analyser = null; freqData = null;
        recBox.classList.add('hidden');

        idleBox.classList.remove('hidden');
    }

    // ---------- live mic level bars ----------
    var audioCtx = null, analyser = null, freqData = null;

    function setupLevels() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx || !recStream) return;
            audioCtx = new Ctx();
            var src = audioCtx.createMediaStreamSource(recStream);
            analyser = audioCtx.createAnalyser();
            analyser.fftSize = 256;
            src.connect(analyser);
            freqData = new Uint8Array(analyser.frequencyBinCount);
        } catch (e) {
            audioCtx = null; analyser = null;
        }
    }

    function paintLevels(t) {
        var bars = recBox.querySelectorAll('.rec-bar');
        if (!bars.length) return;
        for (var i = 0; i < bars.length; i++) {
            var v;
            if (analyser && freqData) {
                var start = Math.floor(i * freqData.length / 2 / bars.length);
                var end = Math.max(start + 1, Math.floor((i + 1) * freqData.length / 2 / bars.length));
                var sum = 0;
                for (var j = start; j < end; j++) sum += freqData[j];
                v = 0.2 + (sum / (end - start) / 255) * 0.8;
            } else {
                v = 0.3 + Math.abs(Math.sin(t / 180 + i * 0.9)) * 0.65;
            }
            bars[i].style.transform = 'scaleY(' + v.toFixed(3) + ')';
        }
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

    // ---------- inbound poller ----------
    // Received replies are folded into the reminders table by the scheduled
    // whatsapp:sync-inbound command; this renders them live without a
    // reload. The endpoint is idempotent per message id, so a poll that
    // races a manual refresh simply returns nothing new.
    var lastId = parseInt(canvas.dataset.lastId || '0', 10);
    var pollUrl = root.dataset.pollUrl;

    if (pollUrl) {
        setInterval(function () {
            var sep = pollUrl.indexOf('?') === -1 ? '?' : '&';
            fetch(pollUrl + sep + 'after=' + lastId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.html) return;
                    var atBottom = canvas.scrollHeight - canvas.scrollTop - canvas.clientHeight < 80;
                    var tpl = document.createElement('template');
                    tpl.innerHTML = data.html;
                    canvas.appendChild(tpl.content);
                    wirePlayer(canvas);
                    lastId = parseInt(data.lastId, 10) || lastId;
                    if (atBottom) canvas.scrollTop = canvas.scrollHeight;
                })
                .catch(function () { /* transient network error — the next tick retries */ });
        }, parseInt(root.dataset.pollInterval, 10) || 5000);
    }
})();
</script>

<style>
    /* ---- Chat canvas texture ---- */
    .wa-canvas {
        background-color: #efeae2;
        background-image:
            radial-gradient(rgb(0 0 0 / 0.028) 1px, transparent 1.2px);
        background-size: 22px 22px;
    }
    .dark .wa-canvas {
        background-color: #0b141a;
        background-image: radial-gradient(rgb(255 255 255 / 0.03) 1px, transparent 1.2px);
    }

    /* ---- Bottom nav hidden on chat pages ---- */
    nav.bottom-nav { display: none !important; }
    .min-h-screen.flex.flex-col.pb-24 { padding-bottom: 0 !important; }

    /* ---- Compose bar inner: constrained width ---- */
    .wa-compose-inner { max-width: 100%; }
    @media (min-width: 768px) { .wa-compose-inner { max-width: 48rem; margin-left: auto; margin-right: auto; } }
    @media (min-width: 1024px) { .wa-compose-inner { max-width: 56rem; } }

    /* ---- WhatsApp-style outgoing bubble ---- */
    .wa-bubble {
        border-radius: 18px 18px 4px 18px;
    }
    [dir="rtl"] .wa-bubble {
        border-radius: 18px 4px 18px 18px;
    }
    .wa-tail {
        clip-path: polygon(100% 0, 100% 100%, 0 100%);
    }
    [dir="rtl"] .wa-tail {
        clip-path: polygon(0 0, 0 100%, 100% 100%);
    }

    /* Voice play button in outgoing bubbles */
    .wa-play-out { background: transparent; color: currentColor; }

    /* EQ bars in played voice messages */
    .wa-eq { background: currentColor; opacity: 0.35; transform-origin: bottom; }
    .dark .wa-eq { opacity: 0.45; }

    /* Tighten text bubble padding. Direction comes from dir="auto" on the
       bubble div: the message's first strong character decides the whole
       block (same rule WhatsApp uses), so mixed lines like
       "12 کارتن x 20.50 = 246 $" keep their stored order. */
    .wa-text { font-size: 0.8125rem; line-height: 1.35; }

    /* Image bubbles: photo bleeds to the bubble edge, caption inside */
    .wa-img { display: block; width: 100%; max-width: 15rem; height: auto; object-fit: cover; cursor: zoom-in; }
    .wa-img-bubble { padding: 0.25rem; overflow: hidden; }

    @keyframes waEq {
        0%, 100% { transform: scaleY(0.45); }
        50% { transform: scaleY(1); }
    }
    .wa-eq { transform-origin: bottom; }

    /* Recording waveform */
    .rec-bar {
        transition: transform 0.12s ease-out;
        transform-origin: center;
        will-change: transform;
    }

    /* ---- Squircle avatar gloss ---- */
    .wa-avatar::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(155deg, rgb(255 255 255 / 0.26), transparent 42%);
        pointer-events: none;
    }
</style>
@endsection
