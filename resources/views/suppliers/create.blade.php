@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="user-plus" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ __('messages.new_supplier') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.supplier') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('people.index') }}"/>
    </div>

    {{-- Identity preview — mirrors the edit page's hero and fills in as you type --}}
    <div class="card relative overflow-hidden p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-accent-500/[0.08] dark:bg-accent-400/[0.1]"></div>
        <div class="relative flex items-center gap-3 min-w-0">
            <div id="preview-avatar" class="w-12 h-12 rounded-full bg-accent-500/10 dark:bg-accent-500/15 border border-dashed border-accent-500/40 text-accent-600 dark:text-accent-400 flex items-center justify-center flex-shrink-0 transition-colors duration-200">
                <span id="preview-initial" class="hidden text-base font-extrabold"></span>
                <x-icon name="user-plus" id="preview-icon" class="w-5 h-5" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h3 id="preview-name" class="text-base font-bold text-ink-900 dark:text-white truncate">{{ __('messages.new_supplier') }}</h3>
                <p class="text-[11px] text-ink-500 dark:text-ink-400">{{ __('messages.supplier') }}</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <x-icon name="user" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.supplier') }}</h3>
            </div>
        </div>
        <form action="{{ route('suppliers.store') }}" method="POST" class="p-4">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="form-label">{{ __('messages.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="form-input" placeholder="{{ __('messages.name') }}">
                    @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-input" placeholder="{{ __('messages.phone') }}">
                    @error('phone') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.address') }}</label>
                    <textarea name="address" rows="2" class="form-input" placeholder="{{ __('messages.address') }}">{{ old('address') }}</textarea>
                    @error('address') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn-primary w-full">
                        <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
                        {{ __('messages.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var nameInput = document.querySelector('input[name="name"]');
        var avatar = document.getElementById('preview-avatar');
        var initial = document.getElementById('preview-initial');
        var icon = document.getElementById('preview-icon');
        var previewName = document.getElementById('preview-name');
        var fallbackName = {{ json_encode(__('messages.new_supplier')) }};

        if (!nameInput || !avatar || !initial || !icon || !previewName) return;

        function showPlaceholder() {
            previewName.textContent = fallbackName;
            initial.classList.add('hidden');
            initial.textContent = '';
            icon.classList.remove('hidden');
            avatar.classList.add('bg-accent-500/10', 'dark:bg-accent-500/15', 'border-dashed', 'border-accent-500/40');
            avatar.classList.remove('bg-accent-500', 'border-solid', 'border-transparent', 'text-white');
        }

        function showInitial(value) {
            previewName.textContent = value;
            initial.textContent = value.charAt(0).toLocaleUpperCase();
            initial.classList.remove('hidden');
            icon.classList.add('hidden');
            avatar.classList.remove('bg-accent-500/10', 'dark:bg-accent-500/15', 'border-dashed', 'border-accent-500/40');
            avatar.classList.add('bg-accent-500', 'border-solid', 'border-transparent', 'text-white');
        }

        nameInput.addEventListener('input', function () {
            var value = nameInput.value.trim();
            if (value === '') { showPlaceholder(); } else { showInitial(value); }
        });
    })();
</script>
@endpush
