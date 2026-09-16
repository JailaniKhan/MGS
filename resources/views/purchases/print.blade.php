<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.purchase') }} #{{ $purchase->id }}</title>
    @fonts
    @include('components.invoice-styles')
</head>
<body>
    <div class="invoice">
        <div class="header">
            <div class="company-info">
                <h1>{{ $company['name'] }}</h1>
                <p>
                    @if($company['address'])
                        {{ $company['address'] }}<br>
                    @endif
                    @if($company['phone'])
                        {{ __('messages.phone') }}: {{ $company['phone'] }}<br>
                    @endif
                    @if($company['email'])
                        {{ __('messages.email') }}: {{ $company['email'] }}
                    @endif
                </p>
            </div>
            <div class="invoice-badge">
                <h2>{{ __('messages.purchase') }}</h2>
                <p>{{ __('messages.number') }}: {{ $billNumber }}</p>
                <p>{{ __('messages.date') }}: {{ local_date($purchase->created_at, 'Y/m/d H:i') }}</p>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-box">
                <h3>{{ __('messages.party') }}</h3>
                <p>
                    <strong>{{ $purchase->party?->name ?? __('messages.unknown') }}</strong><br>
                    @if($purchase->party?->phone)
                        <span>{{ __('messages.phone') }}: </span>{{ $purchase->party->phone }}<br>
                    @endif
                    @if($purchase->party?->address)
                        <span>{{ __('messages.address') }}: </span>{{ $purchase->party->address }}
                    @endif
                </p>
            </div>
            <div class="detail-box">
                <h3>{{ __('messages.purchase_info') }}</h3>
                <p>
                    <span>{{ __('messages.purchase_number') }}: </span><strong>{{ $billNumber }}</strong><br>
                    <span>{{ __('messages.status') }}: </span>
                    @switch($purchase->display_status)
                        @case('paid') {{ __('messages.paid') }} @break
                        @case('completed') {{ __('messages.completed') }} @break
                        @case('processing') {{ __('messages.processing') }} @break
                        @case('cancelled') {{ __('messages.cancelled') }} @break
                        @case('partial') {{ __('messages.partially_paid') }} @break
                        @default {{ __('messages.pending') }}
                    @endswitch<br>
                    <span>{{ __('messages.currency_unit') }}: </span>
                    {{ $purchase->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afghani_afn') }}
                </p>
            </div>
        </div>

        <table>
            <th scope="col"ead>
                <tr>
                    <th scope="col" style="width:40%;">{{ __('messages.items') }}</th>
                    <th scope="col" class="qty" style="width:15%;">كمیت</th>
                    <th scope="col" class="price" style="width:20%;">{{ __('messages.unit_price') }}</th>
                    <th scope="col" class="subtotal" style="width:25%;">{{ __('messages.total_line') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->purchaseItems as $item)
                <tr>
                    <td>{{ $item->product->name }}@if($item->lot_number) <br><span style="font-size:11px;color:#0c8c53;">{{ __('messages.lot_number') }}: {{ $item->lot_number }}</span>@endif</td>
                    <td class="qty">{{ $item->quantity }} {{ $item->product->unit->short_name ?? $item->product->unit->name ?? '' }}</td>
                    <td class="price">{{ money_format($item->unit_price) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                    <td class="subtotal">{{ money_format($item->subtotal) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table class="totals-table">
                @if ($purchase->status !== 'cancelled')
                    <tr>
                        <td>{{ __('messages.paid') }}</td>
                        <td>{{ money_format($purchase->paid_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('messages.current_pending') }}</td>
                        <td>{{ money_format($purchase->remaining_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>{{ __('messages.total_amount') }}</td>
                    <td>{{ money_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                </tr>
                @if ($purchase->status !== 'cancelled' && $purchase->party)
                    <tr class="pending-row">
                        <td>{{ __('messages.total_pending') }} ({{ $purchase->party->name }})</td>
                        <td>{{ money_format($totalPending) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="footer">
            <p>{{ __('messages.business_mgmt_system') }} (MGS) {{ __('messages.built_by') }}</p>
            <p>{{ __('messages.purchase_date') }}: {{ local_date($purchase->created_at, 'Y/m/d H:i') }}</p>
        </div>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">{{ __('messages.print') }}</button>
    </div>
</body>
</html>
