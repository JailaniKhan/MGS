<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.statement') }} — {{ $person->name }}</title>
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
                <h2>{{ __('messages.statement') }}</h2>
                <p>{{ __('messages.number') }}: {{ $statementNumber }}</p>
                <p>{{ __('messages.date') }}: {{ local_date(now(), 'Y/m/d H:i') }}</p>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-box">
                <h3>{{ $personType === 'customer' ? __('messages.customer_info') : __('messages.supplier') }}</h3>
                <p>
                    <strong>{{ $person->name }}</strong><br>
                    @if($person->phone)
                        <span>{{ __('messages.phone') }}: </span>{{ $person->phone }}<br>
                    @endif
                    @if($person->address)
                        <span>{{ __('messages.address') }}: </span>{{ $person->address }}
                    @endif
                </p>
            </div>
            <div class="detail-box">
                <h3>{{ __('messages.cashbook') }}</h3>
                <p>
                    <span>{{ __('messages.currency_unit') }}: </span>{{ $selectedCurrency }}<br>
                    <span>{{ __('messages.cashbook_in_total') }}: </span>{{ number_format((float) ($totals[$selectedCurrency]['in'] ?? 0), 2) }}<br>
                    <span>{{ __('messages.cashbook_out_total') }}: </span>{{ number_format((float) ($totals[$selectedCurrency]['out'] ?? 0), 2) }}
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th scope="col" style="width:16%;">{{ __('messages.date') }}</th>
                    <th scope="col" style="width:44%;">{{ __('messages.description') }}</th>
                    <th scope="col" class="qty" style="width:13%;">{{ __('messages.cashbook_in_total') }}</th>
                    <th scope="col" class="qty" style="width:13%;">{{ __('messages.cashbook_out_total') }}</th>
                    <th scope="col" class="qty" style="width:14%;">{{ __('messages.balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $tx)
                    <tr>
                        <td>{{ local_date($tx->date, 'Y/m/d') }}</td>
                        <td>
                            {{ $tx->label }}
                            @if (! empty($tx->notes))
                                <br><span class="note">{{ $tx->notes }}</span>
                            @endif
                        </td>
                        <td class="qty" style="color:#0c8c53;font-weight:600;">{{ $tx->direction === 'in' ? number_format((float) $tx->amount, 2) : '—' }}</td>
                        <td class="qty" style="color:#b45309;font-weight:600;">{{ $tx->direction === 'in' ? '—' : number_format((float) $tx->amount, 2) }}</td>
                        <td class="qty">{{ number_format((float) ($tx->balance ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;">{{ __('messages.no_transactions') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        @php
            $closing = (float) ($closingBalances[$selectedCurrency] ?? 0);
            $closingSign = $closing < 0 ? '-' : '';
        @endphp
        <div class="totals">
            <table class="totals-table">
                <tr>
                    <td>{{ __('messages.cashbook_in_total') }}</td>
                    <td>{{ number_format((float) ($totals[$selectedCurrency]['in'] ?? 0), 2) }} {{ $selectedCurrency }}</td>
                </tr>
                <tr>
                    <td>{{ __('messages.cashbook_out_total') }}</td>
                    <td>{{ number_format((float) ($totals[$selectedCurrency]['out'] ?? 0), 2) }} {{ $selectedCurrency }}</td>
                </tr>
                <tr class="{{ $closing < 0 ? 'pending-row' : 'total-row' }}">
                    <td>{{ __('messages.balance') }}</td>
                    <td>{{ $closingSign }}{{ number_format(abs($closing), 2) }} {{ $selectedCurrency }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>{{ __('messages.business_mgmt_system') }} (MGS) {{ __('messages.built_by') }}</p>
            <p>{{ __('messages.invoice_date') }}: {{ local_date(now(), 'Y/m/d H:i') }}</p>
        </div>
    </div>

    <x-print-flash/>
    <x-document-actions
        :open="route('cashbook.pdf.open', [$personType, $person->id, 'currency' => $selectedCurrency])"
        :save="route('cashbook.pdf.save', [$personType, $person->id, 'currency' => $selectedCurrency])"
        :share="route('cashbook.pdf.share', [$personType, $person->id, 'currency' => $selectedCurrency])"
        :whatsapp="route('cashbook.pdf.whatsapp', [$personType, $person->id, 'currency' => $selectedCurrency])"
    />
</body>
</html>
