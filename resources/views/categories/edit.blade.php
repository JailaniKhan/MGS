@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('categories.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>
    <h2 class="text-lg font-semibold mb-4">د کتګورۍ سمول</h2>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <form action="{{ route('categories.update', $category) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">نوم</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full bg-[#f53003] text-white py-3 rounded-lg font-medium">ذخیره کول</button>
        </form>
    </div>
@endsection