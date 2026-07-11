@extends('layouts.app')

@section('content')
<div class="page-enter">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.business_card') }}</h2>

    <!-- Business Card Preview -->
    <div class="card overflow-hidden mb-4">
        <div class="bg-brand text-white p-6 text-white text-center">
            <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold mb-1">{{ $company['name'] }}</h3>
            @if($company['address'])
                <p class="text-sm text-primary-100 mb-1">{{ $company['address'] }}</p>
            @endif
            @if($company['phone'])
                <p class="text-sm text-primary-100">📞 {{ $company['phone'] }}</p>
            @endif
            @if($company['email'])
                <p class="text-sm text-primary-100">✉️ {{ $company['email'] }}</p>
            @endif
            @if($company['tax_id'])
                <p class="text-xs text-primary-200 mt-2">🆔 {{ $company['tax_id'] }}</p>
            @endif
        </div>
    </div>

    <!-- Share Options -->
    <div class="card p-4 mb-4">
        <h4 class="section-header mb-3">{{ __('messages.share_card') }}</h4>
        <div class="grid grid-cols-2 gap-3">
            <form action="{{ route('business-card.share-whatsapp') }}" method="POST">
                @csrf
                <button type="submit" class="w-full action-card">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span class="text-[10px] font-bold leading-tight">WhatsApp</span>
                </button>
            </form>
            <button onclick="copyCard()" class="action-card">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/>
                </svg>
                <span class="text-[10px] font-bold leading-tight">{{ __('messages.copy_card') }}</span>
            </button>
        </div>
    </div>

    <!-- Edit Company Info -->
    <div class="card p-4">
        <h4 class="section-header mb-3">{{ __('messages.edit_company_info') }}</h4>
        <form action="{{ route('settings.update') }}" method="POST">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="form-label">{{ __('messages.company_name') }}</label>
                    <input type="text" name="company_name" value="{{ $company['name'] }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('messages.company_phone') }}</label>
                    <input type="text" name="company_phone" value="{{ $company['phone'] }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">{{ __('messages.company_address') }}</label>
                    <textarea name="company_address" rows="2" class="form-input">{{ $company['address'] }}</textarea>
                </div>
                <div>
                    <label class="form-label">{{ __('messages.company_email') }}</label>
                    <input type="email" name="company_email" value="{{ $company['email'] }}" class="form-input">
                </div>
                <button type="submit" class="btn-primary w-full">{{ __('messages.save') }}</button>
            </div>
        </form>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            {{ __('messages.back') }}
        </a>
    </div>
</div>

@push('scripts')
<script>
    function copyCard() {
        const cardText = `{!! $company['name'] !!}\n{!! $company['address'] !!}\n{!! $company['phone'] !!}\n{!! $company['email'] !!}\n\nShared via MGS App`;
        navigator.clipboard.writeText(cardText).then(() => {
            alert('{{ __("messages.copied") }}');
        });
    }
</script>
@endpush
@endsection
