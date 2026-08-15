<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.invoice') }} #{{ $order->id }}</title>
    @fonts
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            direction: rtl;
            padding: 20px;
            color: #333;
            background: #fff;
        }
        .invoice {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 30px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 22px;
            color: #0d9488;
            margin-bottom: 8px;
        }
        .company-info p {
            font-size: 13px;
            color: #555;
            line-height: 1.8;
        }
        .invoice-badge {
            text-align: left;
        }
        .invoice-badge h2 {
            font-size: 20px;
            color: #0d9488;
        }
        .invoice-badge p {
            font-size: 13px;
            color: #555;
            margin-top: 4px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .detail-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            background: #f9fafb;
        }
        .detail-box h3 {
            font-size: 14px;
            color: #0d9488;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .detail-box p {
            font-size: 13px;
            color: #444;
            line-height: 1.8;
        }
        .detail-box p span {
            color: #666;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #0d9488;
            color: #fff;
            padding: 10px 12px;
            font-size: 13px;
            text-align: right;
        }
        td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: right;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        td.qty, td.price, td.subtotal {
            text-align: center;
        }
        td.subtotal {
            font-weight: 600;
        }
        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 12px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals-table td:last-child {
            text-align: left;
            font-weight: 600;
        }
        .totals-table .total-row {
            background: #0d9488;
            color: #fff;
            font-size: 15px;
        }
        .totals-table .total-row td {
            border-bottom: none;
            padding: 12px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        .btn-print {
            background: #0d9488;
            color: #fff;
            border: none;
            padding: 10px 30px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #0f766e;
        }
        @media print {
            body { padding: 0; }
            .invoice { border: none; max-width: 100%; }
            .actions { display: none; }
            th { color: #000; background: #e5e7eb; }
            .invoice-badge { float: left; }
        }
    </style>
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
                <h2>{{ __('messages.invoice') }}</h2>
                <p>{{ __('messages.number') }}: {{ $invoiceNumber }}</p>
                <p>{{ __('messages.date') }}: {{ $order->created_at->format('Y/m/d H:i') }}</p>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-box">
                <h3>{{ __('messages.customer_info') }}</h3>
                <p>
                    <strong>{{ $order->party?->name }}</strong><br>
                    @if($order->party?->phone)
                        <span>{{ __('messages.phone') }}: </span>{{ $order->party->phone }}<br>
                    @endif
                    @if($order->party?->address)
                        <span>{{ __('messages.address') }}: </span>{{ $order->party->address }}
                    @endif
                </p>
            </div>
            <div class="detail-box">
                <h3>{{ __('messages.order_info') }}</h3>
                <p>
                    <span>{{ __('messages.order_number') }}: </span><strong>{{ $order->id }}</strong><br>
                    <span>{{ __('messages.status') }}: </span>
                    @switch($order->display_status)
                        @case('paid') {{ __('messages.paid') }} @break
                        @case('completed') {{ __('messages.completed') }} @break
                        @case('processing') {{ __('messages.processing') }} @break
                        @case('cancelled') {{ __('messages.cancelled') }} @break
                        @default {{ __('messages.pending') }}
                    @endswitch<br>
                    <span>{{ __('messages.currency_unit') }}: </span>
                    {{ $order->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afghani_afn') }}
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:40%;">{{ __('messages.items') }}</th>
                    <th class="qty" style="width:15%;">كمیت</th>
                    <th class="price" style="width:20%;">{{ __('messages.unit_price') }}</th>
                    <th class="subtotal" style="width:25%;">{{ __('messages.total_line') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderItems as $item)
                <tr>
                    <td>{{ $item->product->name }}@if($item->lot_number) <br><span style="font-size:11px;color:#0d9488;">{{ __('messages.lot_number') }}: {{ $item->lot_number }}</span>@endif</td>
                    <td class="qty">{{ $item->quantity }} {{ $item->product->unit->short_name ?? $item->product->unit->name ?? '' }}</td>
                    <td class="price">{{ number_format($item->unit_price) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                    <td class="subtotal">{{ number_format($item->subtotal) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table class="totals-table">
                <tr class="total-row">
                    <td>{{ __('messages.total_amount') }}</td>
                    <td>{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>{{ __('messages.business_mgmt_system') }} (MGS) {{ __('messages.built_by') }}</p>
            <p>{{ __('messages.invoice_date') }}: {{ $order->created_at->format('Y/m/d H:i') }}</p>
        </div>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">{{ __('messages.print') }}</button>
    </div>
</body>
</html>
