@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.edit_product') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('products.update', $product) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required class="form-input">
                @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.category') }}</label>
                <select name="category_id" required class="form-input">
                    <option value="">{{ __('messages.select_option') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.unit') }}</label>
                <select name="unit_id" class="form-input">
                    <option value="">{{ __('messages.select_option') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_id', $product->unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }} @if($unit->short_name)({{ $unit->short_name }})@endif</option>
                    @endforeach
                </select>
                @error('unit_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="form-label">{{ __('messages.price_afn') }}</label>
                    <input type="number" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0" required class="form-input">
                    @error('price') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.stock') }}</label>
                    <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0" required class="form-input">
                    @error('stock') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.description') }}</label>
                <textarea name="description" rows="2" class="form-input">{{ old('description', $product->description) }}</textarea>
                @error('description') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.save') }}</button>
        </form>
    </div>
@endsection