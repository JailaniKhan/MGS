@props([
    'amount',
    'currency' => 'AFN',   // 'AFN' | 'USD'
    // null = auto: 2 decimals when the amount has cents, 0 when whole
    'decimals' => null,
    // When true, prefix +/− inside the isolated numeric run (profit/loss surfaces)
    'sign' => false,
    // Classes for the currency word/symbol; override per surface (hero, tiles…)
    'symbolClass' => 'text-[10px] font-medium text-ink-500 dark:text-ink-400',
])

@php($isUsd = strtoupper((string) $currency) === 'USD')
@php($num = (float) $amount)
@php($dec = $decimals === null ? (((int) round(abs($num) * 100)) % 100 === 0 ? 0 : 2) : (int) $decimals)

<span {{ $attributes->merge(['class' => 'whitespace-nowrap']) }}>
    {{-- Number + symbol form ONE isolated LTR run so bidi never splits them --}}
    <span dir="ltr" class="tabular-nums">@if ($sign){{ $num < 0 ? '-' : '+' }}@endif{{ number_format($sign ? abs($num) : $num, $dec) }}@if ($isUsd)$@endif</span>
    @unless ($isUsd)
        <span class="{{ $symbolClass }}">{{ __('messages.afn') }}</span>
    @endunless
</span>
