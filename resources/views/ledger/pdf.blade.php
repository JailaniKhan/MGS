<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.ledger_for') }} {{ $person->name }}</title>
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
        .page {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #0c8c53;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 24px;
            color: #0c8c53;
            margin-bottom: 8px;
        }
        .company-info p {
            font-size: 13px;
            color: #555;
            line-height: 1.8;
        }
        .ledger-title {
            text-align: left;
        }
        .ledger-title h2 {
            font-size: 22px;
            color: #0c8c53;
            margin-bottom: 4px;
        }
        .ledger-title p {
            font-size: 13px;
            color: #555;
        }
        .person-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .person-section h3 {
            font-size: 14px;
            color: #0c8c53;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .person-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .person-grid p {
            font-size: 13px;
            color: #444;
            line-height: 1.8;
        }
        .person-grid p strong {
            color: #333;
        }
        .balances {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }
        .balance-card {
            flex: 1;
            background: #fff;
            border: 2px solid #0c8c53;
            border-radius: 6px;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .balance-card.opening {
            border-color: #6b7280;
        }
        .balance-card.opening .balance-value {
            color: #6b7280;
        }
        .balance-card.closing .balance-value {
            color: #0c8c53;
        }
        .balance-card .balance-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        .balance-card .balance-value {
            font-size: 16px;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #0c8c53;
            color: #fff;
            padding: 10px 12px;
            font-size: 13px;
            text-align: right;
        }
        td {
            padding: 10px 12px;
            font-size: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: right;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        td.currency-col {
            text-align: center;
            font-weight: 600;
        }
        td.amount-col {
            text-align: left;
            font-weight: 500;
        }
        td.amount-col.positive {
            color: #dc2626;
        }
        td.amount-col.negative {
            color: #059669;
        }
        td.balance-col {
            text-align: left;
            font-weight: 600;
        }
        .summary {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .summary-table {
            width: 350px;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 8px 12px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary-table td:last-child {
            text-align: left;
            font-weight: 600;
        }
        .summary-table .summary-row {
            background: #f3f4f6;
        }
        .summary-table .total-row {
            background: #0c8c53;
            color: #fff;
            font-size: 15px;
        }
        .summary-table .total-row td {
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
            background: #0c8c53;
            color: #fff;
            border: none;
            padding: 10px 30px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #0a6d44;
        }
        @media print {
            body { padding: 0; }
            .page { max-width: 100%; }
            .actions { display: none; }
            th { color: #000; background: #e5e7eb; }
        }
        .no-transactions {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="company-info">
                <h1>{{ $company['name'] }}</h1>
                @if($company['address'])
                    <p>{{ $company['address'] }}</p>
                @endif
                @if($company['phone'])
                    <p>{{ __('messages.phone') }}: {{ $company['phone'] }}</p>
                @endif
                @if($company['email'])
                    <p>{{ __('messages.email') }}: {{ $company['email'] }}</p>
                @endif
            </div>
            <div class="ledger-title">
                <h2>{{ __('messages.ledger') }}</h2>
                <p>{{ $typeLabel }}</p>
                <p>{{ __('messages.date') }}: {{ now()->format('Y/m/d H:i') }}</p>
            </div>
        </div>

        <div class="person-section">
            <h3>د {{ $personLabel }} {{ __('messages.information') }}</h3>
            <div class="person-grid">
                <div>
                    <p><strong>{{ __('messages.name') }}:</strong> {{ $person->name }}</p>
                    @if($person->phone)
                        <p><strong>{{ __('messages.phone') }}:</strong> {{ $person->phone }}</p>
                    @endif
                    @if($person->address)
                        <p><strong>{{ __('messages.address') }}:</strong> {{ $person->address }}</p>
                    @endif
                </div>
                <div>
                    @if($personType === 'customer')
                        <p><strong>{{ __('messages.customer_product') }}:</strong> {{ $person->orders_count ?? $person->orders->count() }}</p>
                    @else
                        <p><strong>{{ __('messages.supplier_purchase_item') }}:</strong> {{ $person->purchases_count ?? $person->purchases->count() }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="balances">
            <div class="balance-card opening">
                <span class="balance-label">{{ __('messages.opening_balance') }}</span>
                <span class="balance-value">
                    @if($openingBalanceAFN > 0 || $openingBalanceUSD > 0)
                        {{ number_format($openingBalanceAFN) }} {{ __('messages.afn') }}
                        @if($openingBalanceUSD > 0)
                            / {{ number_format($openingBalanceUSD) }}$
                        @endif
                    @else
                        {{ __('messages.zero') }}
                    @endif
                </span>
            </div>
            <div class="balance-card closing">
                <span class="balance-label">{{ __('messages.closing_balance') }}</span>
                <span class="balance-value">
                    @if($closingBalanceAFN > 0 || $closingBalanceUSD > 0)
                        {{ number_format($closingBalanceAFN) }} {{ __('messages.afn') }}
                        @if($closingBalanceUSD > 0)
                            / {{ number_format($closingBalanceUSD) }}$
                        @endif
                    @elseif($closingBalanceAFN < 0 || $closingBalanceUSD < 0)
                        {{ __('messages.credit') }}:
                        @if($closingBalanceAFN < 0){{ number_format(abs($closingBalanceAFN)) }} {{ __('messages.afn') }}@endif
                        @if($closingBalanceAFN < 0 && $closingBalanceUSD < 0) / @endif
                        @if($closingBalanceUSD < 0){{ number_format(abs($closingBalanceUSD)) }}$@endif
                    @else
                        {{ __('messages.zero') }}
                    @endif
                </span>
            </div>
        </div>

        @if(count($transactions) > 0)
            <div style="font-size:14px;font-weight:700;color:#0c8c53;margin:20px 0 10px 0;padding-bottom:5px;border-bottom:2px dashed #e5e7eb;">
                {{ __('messages.account_history') }}
            </div>
            <table>
                <th scope="col"ead>
                    <tr>
                        <th scope="col">{{ __('messages.date') }}</th>
                        <th scope="col">{{ __('messages.description') }}</th>
                        <th scope="col">{{ __('messages.reference') }}</th>
                        <th scope="col">{{ __('messages.unit') }}</th>
                        <th scope="col">{{ __('messages.subtotal') }} Dr</th>
                        <th scope="col">Cr</th>
                        <th scope="col">{{ __('messages.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                        <tr>
                            <td>{{ $tx['date']->format('Y/m/d') }}</td>
                            <td>{{ $tx['description'] }}</td>
                            <td style="font-size:11px;">{{ $tx['ref'] }}</td>
                            <td class="currency-col">{{ $tx['currency'] === 'USD' ? '$' : __('messages.afn') }}</td>
                            <td class="amount-col {{ $tx['is_positive'] ? 'positive' : '' }}">
                                {{ $tx['is_positive'] ? number_format($tx['amount']) : '-' }}
                            </td>
                            <td class="amount-col {{ !$tx['is_positive'] ? 'negative' : '' }}">
                                {{ !$tx['is_positive'] ? number_format($tx['amount']) : '-' }}
                            </td>
                            <td class="balance-col">{{ number_format(abs($tx['balance'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-transactions">
                <p>{{ __('messages.no_entries') }}</p>
            </div>
        @endif

        <div style="font-size:14px;font-weight:700;color:#0c8c53;margin:20px 0 10px 0;padding-bottom:5px;border-bottom:2px dashed #e5e7eb;">
            {{ __('messages.summary') }}
        </div>
        <div class="summary">
            <table class="summary-table">
                <tr class="summary-row">
                    <td>{{ __('messages.total_subtotal') }} {{ __('messages.afn') }}</td>
                    <td>{{ number_format($totalOrdersAFN) }} {{ __('messages.afn') }}</td>
                </tr>
                <tr class="summary-row">
                    <td>{{ __('messages.total_subtotal') }} {{ __('messages.usd') }}</td>
                    <td>{{ number_format($totalOrdersUSD) }}$</td>
                </tr>
                <tr class="summary-row">
                    <td>{{ __('messages.returned') }} {{ __('messages.afn') }}</td>
                    <td>{{ number_format($totalReturnedAFN) }} {{ __('messages.afn') }}</td>
                </tr>
                <tr class="summary-row">
                    <td>{{ __('messages.returned') }} {{ __('messages.usd') }}</td>
                    <td>{{ number_format($totalReturnedUSD) }}$</td>
                </tr>
                <tr class="summary-row">
                    <td>{{ __('messages.pending') }} {{ __('messages.afn') }}</td>
                    <td>{{ number_format($remainingAFN) }} {{ __('messages.afn') }}</td>
                </tr>
                <tr class="summary-row">
                    <td>{{ __('messages.pending') }} {{ __('messages.usd') }}</td>
                    <td>{{ number_format($remainingUSD) }}$</td>
                </tr>
                @if($creditAFN > 0 || $creditUSD > 0)
                <tr class="summary-row">
                    <td>{{ __('messages.credit') }} {{ __('messages.afn') }}</td>
                    <td>{{ number_format($creditAFN) }} {{ __('messages.afn') }}</td>
                </tr>
                <tr class="summary-row">
                    <td>{{ __('messages.credit') }} {{ __('messages.usd') }}</td>
                    <td>{{ number_format($creditUSD) }}$</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>{{ __('messages.paid_short') }} {{ __('messages.afn') }}</td>
                    <td>{{ number_format($totalPaidAFN) }} {{ __('messages.afn') }}</td>
                </tr>
                <tr class="total-row">
                    <td>{{ __('messages.paid_short') }} {{ __('messages.usd') }}</td>
                    <td>{{ number_format($totalPaidUSD) }}$</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>{{ __('messages.business_mgmt_system') }} (MGS) {{ __('messages.built_by') }}</p>
        </div>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">{{ __('messages.print') }} / PDF</button>
    </div>
</body>
</html>
