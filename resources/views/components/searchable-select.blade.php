@props([
    'name' => '',
    'selected' => '',
    'required' => false,
    'placeholder' => '',
    'options' => [],
    'selectClass' => '',
])

@php
    $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="relative" data-searchable data-searchable-required="{{ $required ? '1' : '0' }}" data-searchable-options='{{ $optionsJson }}'>
    <input type="text" value="" placeholder="{{ $placeholder }}" autocomplete="off"
        class="form-input pe-10" data-searchable-input>
    <span class="absolute inset-y-0 end-0 flex items-center pe-3.5 pointer-events-none text-ink-400" data-searchable-chevron>
        <x-icon name="chevron-down" class="w-4 h-4" strokeWidth="2"/>
    </span>
    <button type="button" tabindex="-1" data-searchable-clear
        class="hidden absolute inset-y-0 end-0 flex items-center pe-3.5 text-ink-400 hover:text-danger-500 transition-colors">
        <x-icon name="x-mark" class="w-4 h-4" strokeWidth="2"/>
    </button>
    <ul data-searchable-list
        class="hidden absolute z-50 start-0 end-0 mt-1 max-h-64 overflow-y-auto rounded-xl border border-ink-200 dark:border-ink-700 bg-white dark:bg-ink-900 shadow-xl shadow-ink-900/10 py-1"
        role="listbox"></ul>

    <select name="{{ $name }}" tabindex="-1" aria-hidden="true" class="sr-only {{ $selectClass }}" data-searchable-select>
        @foreach ($options as $option)
            <option value="{{ $option['value'] ?? '' }}"
                @if ((string) ($option['value'] ?? '') === (string) $selected) selected @endif
                @if (array_key_exists('price', $option) && $option['price'] !== null && $option['price'] !== '') data-price="{{ $option['price'] }}" @endif
                @if (array_key_exists('price_currency', $option) && $option['price_currency']) data-price-currency="{{ $option['price_currency'] }}" @endif
                @if (array_key_exists('stock', $option) && $option['stock'] !== null && $option['stock'] !== '') data-stock="{{ $option['stock'] }}" @endif
                @if (array_key_exists('lot', $option) && $option['lot'] !== null && $option['lot'] !== '') data-lot="{{ $option['lot'] }}" @endif
                @if (array_key_exists('purchase_id', $option) && $option['purchase_id']) data-purchase-id="{{ $option['purchase_id'] }}" @endif
                @if (array_key_exists('lots', $option) && $option['lots'] !== null && $option['lots'] !== '') data-lots="{{ $option['lots'] }}" @endif
                @if (array_key_exists('products', $option) && $option['products'] !== null && $option['products'] !== '') data-products="{{ $option['products'] }}" @endif
            >{{ $option['label'] ?? '' }}</option>
        @endforeach
    </select>
</div>

@once
@push('scripts')
<script>
(function () {
    'use strict';

    function mgsOptions(root) {
        var raw = root.getAttribute('data-searchable-options');
        if (!raw) return [];
        try { return JSON.parse(raw); } catch (e) { return []; }
    }
    function mgsValue(root) {
        var select = root.querySelector('[data-searchable-select]');
        return select ? select.value : '';
    }

    function mgsSyncDisplay(root) {
        var input = root.querySelector('[data-searchable-input]');
        var select = root.querySelector('[data-searchable-select]');
        var clear = root.querySelector('[data-searchable-clear]');
        var chevron = root.querySelector('[data-searchable-chevron]');
        var opts = mgsOptions(root);
        var cur = null;
        for (var i = 0; i < opts.length; i++) {
            if (String(opts[i].value) === String(select.value)) { cur = opts[i]; break; }
        }
        input.value = cur ? (cur.label || '') : '';
        if (clear) clear.classList.toggle('hidden', !select.value);
        if (chevron) chevron.classList.toggle('hidden', !!select.value);
        input.classList.remove('ring-danger-500/30');
    }

    function mgsRender(root) {
        var input = root.querySelector('[data-searchable-input]');
        var list = root.querySelector('[data-searchable-list]');
        var opts = mgsOptions(root);
        var q = input.value.trim().toLowerCase();
        // Optional currency filter (purchase form): options priced in the
        // other currency disappear; options with no price stay visible.
        var currencyFilter = root.getAttribute('data-searchable-currency');
        var lastGroup = null;
        list.innerHTML = '';
        root._highlighted = 0;
        opts.forEach(function (o, i) {
            if (currencyFilter && o.price_currency && o.price_currency !== currencyFilter) return;
            var hay = ((o.label || '') + ' ' + (o.sublabel || '')).toLowerCase();
            if (q && hay.indexOf(q) === -1) return;
            if (o.group && o.group !== lastGroup) {
                lastGroup = o.group;
                var h = document.createElement('li');
                h.className = 'px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500 bg-ink-100/70 dark:bg-ink-800/70';
                h.textContent = o.group;
                list.appendChild(h);
            }
            var li = document.createElement('li');
            li.className = 'px-3 py-2.5 text-sm text-ink-800 dark:text-ink-100 cursor-pointer hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors';
            li.setAttribute('data-searchable-option', i);
            var row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-2';
            var left = document.createElement('div');
            left.className = 'min-w-0';
            var label = document.createElement('div');
            label.className = 'truncate font-medium';
            label.textContent = o.label || '';
            left.appendChild(label);
            if (o.sublabel) {
                var sub = document.createElement('div');
                sub.className = 'text-[11px] text-ink-400 dark:text-ink-500 truncate';
                sub.textContent = o.sublabel;
                left.appendChild(sub);
            }
            row.appendChild(left);
            var badges = [];
            if (o.stock !== undefined && o.stock !== null && o.stock !== '') badges.push('{{ __('messages.stock') }}: ' + o.stock);
            if (o.price !== undefined && o.price !== null && o.price !== '') badges.push(o.price + ' ' + (o.price_currency === 'USD' ? '$' : '{{ __('messages.afn') }}'));
            if (badges.length) {
                var right = document.createElement('div');
                right.className = 'text-end text-[11px] font-semibold text-ink-500 dark:text-ink-400 flex-shrink-0';
                right.textContent = badges.join(' · ');
                row.appendChild(right);
            }
            li.appendChild(row);
            list.appendChild(li);
        });
    }

    function mgsHighlight(root) {
        var list = root.querySelector('[data-searchable-list]');
        var items = list.querySelectorAll('[data-searchable-option]');
        items.forEach(function (li, i) {
            li.classList.toggle('bg-primary-50', i === root._highlighted);
            li.classList.toggle('dark:bg-primary-900/20', i === root._highlighted);
        });
    }

    // The dropdown is position:fixed so it can never be clipped by
    // overflow-hidden ancestors (cards, list rows, tables). Placed in
    // viewport coordinates; flips above the input when there is no
    // room below it.
    function mgsPosition(root) {
        var input = root.querySelector('[data-searchable-input]');
        var list = root.querySelector('[data-searchable-list]');
        var r = input.getBoundingClientRect();
        var MAX_H = 256; /* matches the list's max-h-64 */
        var spaceBelow = window.innerHeight - r.bottom - 8;
        var openUp = spaceBelow < MAX_H && r.top > spaceBelow;
        list.style.position = 'fixed';
        list.style.top = openUp
            ? Math.max(8, r.top - MAX_H - 4) + 'px'
            : r.bottom + 4 + 'px';
        list.style.left = r.left + 'px';
        list.style.right = 'auto';
        list.style.width = r.width + 'px';
    }

    // Keep the dropdown glued to its input while it is open.
    var mgsUntrack = function () {};
    function mgsTrack(root) {
        mgsUntrack();
        var move = function () {
            mgsPosition(root);
        };
        window.addEventListener('scroll', move, true);
        window.addEventListener('resize', move);
        mgsUntrack = function () {
            window.removeEventListener('scroll', move, true);
            window.removeEventListener('resize', move);
        };
    }

    function mgsOpen(root) {
        mgsRender(root);
        mgsHighlight(root);
        var list = root.querySelector('[data-searchable-list]');
        list.classList.remove('hidden');
        mgsPosition(root);
        mgsTrack(root);
    }

    function mgsClose(root) {
        root.querySelector('[data-searchable-list]').classList.add('hidden');
        mgsUntrack();
        mgsSyncDisplay(root);
    }

    function mgsPick(root, option) {
        var select = root.querySelector('[data-searchable-select]');
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        mgsClose(root);
    }

    function mgsNav(root, delta) {
        var list = root.querySelector('[data-searchable-list]');
        var items = list.querySelectorAll('[data-searchable-option]');
        if (!items.length) return;
        root._highlighted = (root._highlighted + delta + items.length) % items.length;
        mgsHighlight(root);
        items[root._highlighted].scrollIntoView({ block: 'nearest' });
    }

    window.initSearchableSelect = function initSearchableSelect(root) {
        if (!root || root.dataset.searchableInitialized) return;
        root.dataset.searchableInitialized = '1';

        var input = root.querySelector('[data-searchable-input]');
        var list = root.querySelector('[data-searchable-list]');
        var clear = root.querySelector('[data-searchable-clear]');
        var select = root.querySelector('[data-searchable-select]');

        input.addEventListener('focus', function () { mgsOpen(root); input.select(); });
        input.addEventListener('input', function () {
            mgsRender(root);
            mgsHighlight(root);
            list.classList.remove('hidden');
            mgsPosition(root);
        });
        input.addEventListener('click', function () { mgsOpen(root); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (list.classList.contains('hidden')) { mgsOpen(root); } else { mgsNav(root, 1); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!list.classList.contains('hidden')) mgsNav(root, -1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (list.classList.contains('hidden')) { mgsOpen(root); return; }
                var items = list.querySelectorAll('[data-searchable-option]');
                if (!items.length) return;
                var idx = root._highlighted < items.length ? root._highlighted : 0;
                var opts = mgsOptions(root);
                mgsPick(root, opts[parseInt(items[idx].getAttribute('data-searchable-option'))]);
            } else if (e.key === 'Escape') {
                mgsClose(root);
            }
        });
        input.addEventListener('blur', function () { setTimeout(function () { mgsClose(root); }, 120); });

        list.addEventListener('mousedown', function (e) {
            var li = e.target.closest('[data-searchable-option]');
            if (!li) return;
            e.preventDefault();
            var opts = mgsOptions(root);
            mgsPick(root, opts[parseInt(li.getAttribute('data-searchable-option'))]);
        });

        if (clear) {
            clear.addEventListener('mousedown', function (e) { e.preventDefault(); });
            clear.addEventListener('click', function () {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                input.value = '';
                mgsClose(root);
            });
        }

        var form = root.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (root.dataset.searchableRequired === '1' && !mgsValue(root)) {
                    e.preventDefault();
                    input.classList.add('ring-danger-500/30');
                    input.focus();
                }
            });
        }

        mgsSyncDisplay(root);
    };

    function boot() {
        document.querySelectorAll('[data-searchable]').forEach(window.initSearchableSelect);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
@endonce
