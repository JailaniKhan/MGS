@extends('layouts.app')

@section('content')
@php
    $initials = mb_strtoupper(mb_substr($supplier->name, 0, 1));
    $avatarClass = ['bg-accent-500', 'bg-secondary-500', 'bg-brand'][crc32($supplier->name) % 3];
@endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="pencil-square" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ __('messages.edit_supplier') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.supplier') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('people.index') }}"/>
    </div>

    {{-- Identity hero --}}
    <div class="card relative overflow-hidden p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-accent-500/[0.08] dark:bg-accent-400/[0.1]"></div>
        <div class="relative">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 rounded-full flex items-center justify-center text-base font-extrabold text-white flex-shrink-0 {{ $avatarClass }}">
                    {{ $initials }}
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-ink-900 dark:text-white truncate">{{ $supplier->name }}</h3>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400">{{ __('messages.supplier') }}</p>
                </div>
            </div>
            @if ($supplier->phone || $supplier->address)
                <div class="mt-3 pt-3 border-t border-ink-100 dark:border-ink-700/30 space-y-2.5">
                    @if ($supplier->phone)
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                                <x-icon name="phone" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                            </div>
                            <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.phone') }}:</span>
                            <span class="text-ink-700 dark:text-ink-300 font-medium tabular-nums truncate" dir="ltr">{{ $supplier->phone }}</span>
                        </div>
                    @endif
                    @if ($supplier->address)
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                                <x-icon name="map-pin" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                            </div>
                            <span class="text-ink-500 dark:text-ink-400 flex-shrink-0">{{ __('messages.address') }}:</span>
                            <span class="text-ink-700 dark:text-ink-300 font-medium truncate">{{ $supplier->address }}</span>
                        </div>
                    @endif
                </div>
            @endif
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
        <form action="{{ route('suppliers.update', $supplier) }}" method="POST" class="p-4">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="form-label">{{ __('messages.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required class="form-input" placeholder="{{ __('messages.name') }}">
                    @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="form-input" placeholder="{{ __('messages.phone') }}">
                    @error('phone') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.address') }}</label>
                    <textarea name="address" rows="2" class="form-input" placeholder="{{ __('messages.address') }}">{{ old('address', $supplier->address) }}</textarea>
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
