@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('products.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>
    <h2 class="text-lg font-semibold mb-4">د محصول سمول</h2>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <form action="{{ route('products.update', $product) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">نوم</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">کتګوري</label>
                <select name="category_id" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                    <option value="">-- انتخاب --</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">قیمت (افغ)</label>
                    <input type="number" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                    @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">موجودي</label>
                    <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                    @error('stock') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">تفصیل</label>
                <textarea name="description" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">{{ old('description', $product->description) }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full bg-[#f53003] text-white py-3 rounded-lg font-medium">ذخیره کول</button>
        </form>
    </div>
@endsection