@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('units.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>
    <h2 class="text-lg font-semibold mb-4">نوی واحد</h2>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <form action="{{ route('units.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">نوم</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="لکه: کیلوګرام, کارتون, ټوټه"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">لنډ نوم (اختیاري)</label>
                <input type="text" name="short_name" value="{{ old('short_name') }}" placeholder="لکه: کیلو, کار, ټ"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                @error('short_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full bg-[#f53003] text-white py-3 rounded-lg font-medium">ثبتول</button>
        </form>
    </div>
@endsection