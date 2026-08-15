@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_product') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
                @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.lot_number') }}</label>
                <input type="text" name="lot_number" value="{{ old('lot_number') }}" class="form-input" placeholder="{{ __('messages.lot_auto_generate') }}">
                <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.lot_help') }}</p>
                @error('lot_number') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.category') }}</label>
                <x-searchable-select name="category_id" required placeholder="{{ __('messages.select_option') }}" :selected="old('category_id')" :options="$categoryOptions" />
                @error('category_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.unit') }}</label>
                <x-searchable-select name="unit_id" placeholder="{{ __('messages.select_option') }}" :selected="old('unit_id')" :options="$unitOptions" />
                @error('unit_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="form-label">{{ __('messages.price_afn') }}</label>
                    <input type="number" name="price" value="{{ old('price') }}" step="0.01" min="0" required dir="ltr" inputmode="decimal" class="form-input">
                    @error('price') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.stock') }}</label>
                    <input type="number" name="stock" value="{{ old('stock', 0) }}" min="0" required dir="ltr" inputmode="numeric" class="form-input">
                    @error('stock') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.description') }}</label>
                <textarea name="description" rows="2" class="form-input">{{ old('description') }}</textarea>
                @error('description') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.submit') }}</button>
        </form>
    </div>
@endsection