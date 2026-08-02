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
                <label class="form-label">{{ __('messages.barcode') }}</label>
                <div class="flex gap-2">
                    <input type="text" name="barcode" id="barcode-input" value="{{ old('barcode') }}" class="form-input flex-1" placeholder="{{ __('messages.scan_or_enter_barcode') }}">
                    <button type="button" onclick="scanBarcode()" class="btn-secondary btn-sm whitespace-nowrap">
                        <x-icon name="qr-code" class="w-4 h-4"/>
                        {{ __('messages.scan') }}
                    </button>
                </div>
                <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.barcode_help') }}</p>
                @error('barcode') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.category') }}</label>
                <select name="category_id" required class="form-select">
                    <option value="">{{ __('messages.select_option') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.unit') }}</label>
                <select name="unit_id" class="form-select">
                    <option value="">{{ __('messages.select_option') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} @if($unit->short_name)({{ $unit->short_name }})@endif</option>
                    @endforeach
                </select>
                @error('unit_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="form-label">{{ __('messages.price_afn') }}</label>
                    <input type="number" name="price" value="{{ old('price') }}" step="0.01" min="0" required class="form-input">
                    @error('price') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.stock') }}</label>
                    <input type="number" name="stock" value="{{ old('stock', 0) }}" min="0" required class="form-input">
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

    <!-- Barcode Scanner Modal -->
    <div id="scanner-modal" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center hidden">
        <div class="bg-white dark:bg-[#16181c] rounded-2xl p-6 mx-4 max-w-sm w-full">
            <div class="text-center mb-4">
                <h3 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.scan_barcode') }}</h3>
                <p class="text-sm text-ink-500">{{ __('messages.point_camera_at_barcode') }}</p>
            </div>
            <div id="scanner-container" class="relative w-full aspect-video bg-ink-900 rounded-xl overflow-hidden mb-4">
                <video id="scanner-video" class="w-full h-full object-cover"></video>
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="w-3/4 h-0.5 bg-danger-500 animate-pulse"></div>
                </div>
            </div>
            <div id="scanner-result" class="text-center text-sm font-medium text-ink-700 dark:text-ink-300 mb-4"></div>
            <div class="flex gap-3">
                <button type="button" onclick="closeScanner()" class="btn-secondary flex-1">{{ __('messages.cancel') }}</button>
            </div>
        </div>
    </div>

@push('scripts')
<script>
    let scannerStream = null;

    function scanBarcode() {
        const modal = document.getElementById('scanner-modal');
        modal.classList.remove('hidden');

        // Try to use camera for barcode scanning
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(stream => {
                    scannerStream = stream;
                    const video = document.getElementById('scanner-video');
                    video.srcObject = stream;
                    video.play();

                    // Use BarcodeDetector API if available
                    if ('BarcodeDetector' in window) {
                        const barcodeDetector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'] });
                        detectBarcode(video, barcodeDetector);
                    } else {
                        // Fallback: manual entry
                        document.getElementById('scanner-result').textContent = '{{ __("messages.manual_entry_hint") }}';
                    }
                })
                .catch(err => {
                    document.getElementById('scanner-result').textContent = '{{ __("messages.camera_not_available") }}';
                });
        } else {
            document.getElementById('scanner-result').textContent = '{{ __("messages.camera_not_supported") }}';
        }
    }

    async function detectBarcode(video, detector) {
        try {
            const barcodes = await detector.detect(video);
            if (barcodes.length > 0) {
                document.getElementById('barcode-input').value = barcodes[0].rawValue;
                closeScanner();
                return;
            }
        } catch (e) {}
        if (scannerStream) {
            requestAnimationFrame(() => detectBarcode(video, detector));
        }
    }

    function closeScanner() {
        if (scannerStream) {
            scannerStream.getTracks().forEach(track => track.stop());
            scannerStream = null;
        }
        document.getElementById('scanner-modal').classList.add('hidden');
        document.getElementById('scanner-video').srcObject = null;
    }
</script>
@endpush
@endsection