@extends('layouts.app')

@section('content')
    @php
        // One row per non-empty pool; a zero pool just means this currency was
        // never traded, which is noise on the shelf. Low-stock follows the app
        // rule: a pool runs low only when it exists (> 0) and sits under the
        // threshold.
        $pools = collect([
            [
                'currency' => 'AFN',
                'qty' => (int) $product->stock_afn,
                'lot' => $poolLots['AFN'],
                'price' => (float) $product->price,
                'label' => __('messages.afn'),
            ],
            [
                'currency' => 'USD',
                'qty' => (int) $product->stock_usd,
                'lot' => $poolLots['USD'],
                'price' => (float) ($product->price_usd ?? 0),
                'label' => __('messages.usd'),
            ],
        ])
            ->filter(fn ($pool) => $pool['qty'] > 0 || $pool['price'] > 0)
            ->map(function ($pool) use ($threshold) {
                $pool['is_low'] = $threshold > 0 && $pool['qty'] > 0 && $pool['qty'] < $threshold;

                return $pool;
            });

        $movementLinks = [
            'order' => fn ($id) => route('orders.show', $id),
            'purchase' => fn ($id) => route('purchases.show', $id),
            'order_return' => fn ($id) => route('orders.returns.show', $id),
            'purchase_return' => fn ($id) => route('purchases.returns.show', $id),
        ];
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                <x-icon name="cube" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ $product->name }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                    {{ trim(($product->category?->name ?? __('messages.uncategorized')).(filled($product->unit) ? ' · '.($product->unit->short_name ?? $product->unit->name) : '')) }}
                </p>
            </div>
        </div>
        <x-back-button href="{{ route('inventory.index') }}"/>
    </div>

    {{-- Prices --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-brand/[0.08] dark:bg-brand/[0.12]"></div>
        <div class="relative">
            <span class="metric-label">{{ __('messages.price') }}</span>
            <div class="mt-1.5 space-y-1">
                @if ((float) $product->price > 0)
                    <p class="flex items-baseline gap-1.5 text-2xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">
                        {{ number_format((float) $product->price, 2) }}
                        <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-brand/10 text-brand leading-none">{{ __('messages.afn') }}</span>
                    </p>
                @endif
                @if ((float) ($product->price_usd ?? 0) > 0)
                    <p class="flex items-baseline gap-1.5 text-2xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">
                        {{ number_format((float) $product->price_usd, 2) }}
                        <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 leading-none">{{ __('messages.usd') }}</span>
                    </p>
                @endif
                @if ((float) $product->price <= 0 && (float) ($product->price_usd ?? 0) <= 0)
                    <p class="text-sm font-bold text-ink-400 dark:text-ink-500">&mdash;</p>
                @endif
            </div>
            @if (filled($product->barcode))
                <p class="mt-2 text-[11px] text-ink-500 dark:text-ink-400 tabular-nums" dir="ltr">{{ $product->barcode }}</p>
            @endif
        </div>
    </div>

    {{-- Stock pools + lots --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-brand"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.current_stock') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @forelse ($pools as $pool)
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between gap-3 tabular-nums">
                        <div class="min-w-0">
                            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-1.5 py-0.5 rounded leading-none {{ $pool['currency'] === 'AFN' ? 'bg-brand/10 text-brand' : 'bg-secondary-500/10 text-secondary-600 dark:text-secondary-400' }}">{{ $pool['label'] }}</span>
                            <p class="mt-1 text-lg font-extrabold {{ $pool['is_low'] ? 'text-danger-600 dark:text-danger-400' : 'text-ink-900 dark:text-white' }}">
                                {{ $pool['qty'] }} <span class="text-xs font-bold text-ink-400 dark:text-ink-500">{{ $product->unit?->short_name ?? $product->unit?->name ?? __('messages.units') }}</span>
                                @if($pool['is_low'])
                                    <span class="align-middle text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-danger-50 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400 border border-danger-200 dark:border-danger-700/50">{{ __('messages.low_stock_short') }}</span>
                                @endif
                            </p>
                        </div>
                        @if (filled($pool['lot']))
                            <div class="text-end min-w-0 ms-3">
                                <p class="text-[10px] font-semibold text-ink-400 dark:text-ink-500">{{ __('messages.lot_number') }}</p>
                                <p class="text-xs font-bold text-ink-700 dark:text-ink-300 break-all" dir="ltr">{{ $pool['lot'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <x-empty-state title="{{ __('messages.no_results') }}">
                    <x-icon name="cube" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    {{-- Description --}}
    @if (filled($product->description))
        <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.15s;">
            <span class="metric-label">{{ __('messages.description') }}</span>
            <p class="mt-1 text-sm text-ink-700 dark:text-ink-300 whitespace-pre-line">{{ $product->description }}</p>
        </div>
    @endif

    {{-- Recent stock movements --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-secondary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.stock') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @forelse ($movements as $movement)
                @php
                    $positive = $movement->quantity_change >= 0;
                    $resolver = $movementLinks[$movement->reference_type] ?? null;
                    $url = $resolver ? $resolver($movement->reference_id) : null;
                    $typeLabel = match ($movement->movement_type) {
                        'sale' => __('messages.type_sale'),
                        'purchase' => __('messages.purchase'),
                        'adjustment' => __('messages.stock_adjusted'),
                        default => __('messages.'.($positive ? 'return_order' : 'cancelled')),
                    };
                @endphp
                @if ($url)
                    <a href="{{ $url }}" class="list-row block">
                @else
                    <div class="list-row block">
                @endif
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-8 h-8 rounded-lg {{ $positive ? 'bg-primary-50 dark:bg-primary-900/25 text-primary-600 dark:text-primary-400' : 'bg-danger-50 dark:bg-danger-900/25 text-danger-500 dark:text-danger-400' }} flex items-center justify-center flex-shrink-0">
                                <x-icon :name="$positive ? 'arrow-trending-up' : 'arrow-trending-down'" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $typeLabel }}</div>
                                <div class="text-[11px] text-ink-500 dark:text-ink-400 tabular-nums"><bdi>{{ local_date($movement->created_at, 'Y/m/d H:i') }}</bdi></div>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 ms-3">
                            <div class="text-sm font-extrabold tabular-nums {{ $positive ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500' }}" dir="ltr">
                                {{ ($positive ? '+' : '').number_format((float) $movement->quantity_change) }}
                                <span class="text-[9px] font-bold text-ink-400">{{ $movement->currency }}</span>
                            </div>
                        </div>
                @if ($url)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <x-empty-state title="{{ __('messages.no_results') }}">
                    <x-icon name="arrow-path" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    {{-- Edit shortcut --}}
    <a href="{{ route('products.edit', $product) }}" class="btn-primary w-full page-enter" style="animation-delay: 0.25s;">
        <x-icon name="pencil-square" class="w-4 h-4" strokeWidth="2"/>
        {{ __('messages.edit') }}
    </a>
@endsection
