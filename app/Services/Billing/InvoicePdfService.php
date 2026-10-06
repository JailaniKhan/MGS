<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Render an order invoice / purchase bill as a printable PDF.
 *
 * Shares BackupPdfService's mPDF setup: Lateef shapes the shop's Dari/Pashto
 * natively (DejaVu and modern Noto builds render Arabic script as tofu or
 * carry OTL tables mPDF 8.x cannot parse), DejaVu renders the Latin/number
 * runs crisp. tempDir must exist before Mpdf boots — ensured in makeMpdf().
 *
 * On device the bytes go to the native shell over the bridge
 * ('Print.File' → system viewer/share sheet); in the browser the HTML print
 * page stays the artifact.
 */
class InvoicePdfService
{
    protected const BRAND = '#10ae64';

    protected const INK = '#1f2328';

    public function forOrder(Order $order, array $company, string $invoiceNumber, float $totalPending): string
    {
        $order->loadMissing('orderItems.product.unit');
        // party is a computed accessor (customer/supplier morph), not a
        // relation — eager loading it by name throws RelationNotFoundException.
        $party = $order->party;

        $items = $order->orderItems->map(fn ($item) => $this->itemRow($item))->all();

        return $this->render([
            'title' => __('messages.invoice'),
            'number' => $invoiceNumber,
            'company' => $company,
            'party_title' => __('messages.customer_info'),
            'party' => $party,
            'info_title' => __('messages.order_info'),
            'info_lines' => [
                __('messages.order_number').': '.$order->id,
                __('messages.status').': '.$this->statusLabel($order->display_status),
                __('messages.currency_unit').': '.($order->currency === 'USD'
                    ? __('messages.usd_with_paren').'$)'
                    : __('messages.afghani_afn')),
            ],
            'date' => $order->created_at,
            'currency' => $order->currency,
            'items' => $items,
            'show_totals' => $order->status !== 'cancelled',
            'paid' => (float) $order->paid_amount,
            'pending' => (float) $order->remaining_amount,
            'total' => (float) $order->total_amount,
            'party_pending' => $order->party ? $totalPending : null,
            'party_pending_label' => __('messages.total_pending').' ('.($order->party?->name ?? '').')',
        ]);
    }

    public function forPurchase(Purchase $purchase, array $company, string $billNumber, float $totalPending): string
    {
        $purchase->loadMissing('purchaseItems.product.unit');
        // party is a computed accessor (customer/supplier morph), not a
        // relation — eager loading it by name throws RelationNotFoundException.
        $party = $purchase->party;

        $items = $purchase->purchaseItems->map(fn ($item) => $this->itemRow($item))->all();

        return $this->render([
            'title' => __('messages.purchase'),
            'number' => $billNumber,
            'company' => $company,
            'party_title' => __('messages.supplier'),
            'party' => $party,
            'info_title' => __('messages.purchase_info'),
            'info_lines' => [
                __('messages.purchase_number').': '.$purchase->id,
                __('messages.status').': '.$this->statusLabel($purchase->display_status),
                __('messages.currency_unit').': '.($purchase->currency === 'USD'
                    ? __('messages.usd_with_paren').'$)'
                    : __('messages.afghani_afn')),
            ],
            'date' => $purchase->created_at,
            'currency' => $purchase->currency,
            'items' => $items,
            'show_totals' => $purchase->status !== 'cancelled',
            'paid' => (float) $purchase->paid_amount,
            'pending' => (float) $purchase->remaining_amount,
            'total' => (float) $purchase->total_amount,
            'party_pending' => $purchase->party ? $totalPending : null,
            'party_pending_label' => __('messages.total_pending').' ('.($purchase->party?->name ?? '').')',
        ]);
    }

    /**
     * Render a customer/supplier cashbook statement as a printable PDF — the
     * printed twin of cashbook/person.blade.php. Cashbook rows carry their
     * running balance; linked orders/purchases are context lines that never
     * move it, exactly as on screen.
     *
     * @param  array<string, array{in: float, out: float, net: float}>  $totals
     * @param  Collection<int, object>  $transactions
     */
    public function forStatement(
        Customer|Supplier $person,
        string $personType,
        string $currency,
        array $totals,
        $transactions,
        ?float $closing,
        array $company,
        string $number,
    ): string {
        $rows = [];

        foreach ($transactions as $tx) {
            try {
                $date = Carbon::parse($tx->date)->format('Y/m/d');
            } catch (\Throwable) {
                $date = (string) $tx->date;
            }

            $isIn = ($tx->direction ?? 'out') === 'in';

            $rows[] = [
                'date' => $date,
                'label' => $tx->label.(! empty($tx->notes) ? ' — '.$tx->notes : ''),
                'in' => $isIn ? (float) $tx->amount : null,
                'out' => $isIn ? null : (float) $tx->amount,
                'balance' => isset($tx->balance) ? (float) $tx->balance : null,
            ];
        }

        $totals = $totals[$currency] ?? ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];

        $mpdf = $this->makeMpdf();
        $mpdf->SetTitle(__('messages.statement').' '.$number);
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($this->statementHtml([
            'number' => $number,
            'company' => $company,
            'person' => $person,
            'person_type' => $personType,
            'currency' => $currency,
            'rows' => $rows,
            'total_in' => (float) $totals['in'],
            'total_out' => (float) $totals['out'],
            'closing' => $closing ?? (float) $totals['net'],
        ]));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    protected function itemRow($item): array
    {
        return [
            'name' => $item->product?->name ?? '-',
            'lot' => $item->lot_number,
            'qty' => trim((string) $item->quantity),
            'unit' => trim((string) ($item->product?->unit?->short_name ?? $item->product?->unit?->name ?? '')),
            'unit_price' => (float) $item->unit_price,
            'subtotal' => (float) $item->subtotal,
        ];
    }

    /**
     * Wrap Arabic-script text (unit names, the افغانی/ډالر currency words) so
     * it renders in its own Lateef run — DejaVu, which .ltr forces for the
     * surrounding digits, has the codepoints but cannot shape them.
     */
    private function rtlSpan(string $text): string
    {
        if ($text === '' || ! preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return e($text);
        }

        return '<span class="rtl-run">'.e($text).'</span>';
    }

    /**
     * Statement body: company header, party + cashbook info boxes, the
     * transaction table with a running balance, then the totals block.
     *
     * @param  array<string, mixed>  $doc
     */
    protected function statementHtml(array $doc): string
    {
        $company = $doc['company'];
        $currencySymbol = $doc['currency'] === 'USD' ? '$' : __('messages.afn');
        $person = $doc['person'];

        $contact = implode('', array_filter([
            ! empty($company['address']) ? e($company['address']).'<br>' : '',
            ! empty($company['phone']) ? e(__('messages.phone')).': '.e($company['phone']).'<br>' : '',
            ! empty($company['email']) ? e(__('messages.email')).': '.e($company['email']) : '',
        ]));

        $rows = '';
        foreach ($doc['rows'] as $row) {
            $rows .= '<tr>'
                .'<td class="ltr">'.e($row['date']).'</td>'
                .'<td>'.e($row['label']).'</td>'
                .'<td class="ltr amount-in">'.($row['in'] !== null ? money_format($row['in']) : '—').'</td>'
                .'<td class="ltr amount-out">'.($row['out'] !== null ? money_format($row['out']) : '—').'</td>'
                .'<td class="ltr">'.($row['balance'] !== null ? money_format($row['balance']) : '—').'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="text-align:center;">'.e(__('messages.no_transactions')).'</td></tr>';
        }

        try {
            $stamp = Carbon::now()->format('Y/m/d H:i');
        } catch (\Throwable) {
            $stamp = '';
        }

        $closing = (float) $doc['closing'];
        $closingClass = $closing < 0 ? 'pending-row' : 'total-row';
        $closingText = ($closing < 0 ? '-' : '').money_format(abs($closing));

        return $this->style().'
            <div class="header">
                <div class="company-info">
                    <h1>'.e($company['name'] ?: 'MGS').'</h1>
                    <p>'.$contact.'</p>
                </div>
                <div class="invoice-badge">
                    <h2>'.e(__('messages.statement')).'</h2>
                    <p>'.e(__('messages.number')).': <span class="ltr">'.e($doc['number']).'</span></p>
                    <p>'.e(__('messages.date')).': <span class="ltr">'.e($stamp).'</span></p>
                </div>
            </div>
            <div class="details-grid">
                <div class="detail-box">
                    <h3>'.e($doc['person_type'] === 'customer' ? __('messages.customer_info') : __('messages.supplier')).'</h3>
                    <p><strong>'.e($person?->name ?? '-').'</strong><br>'
                    .($person?->phone ? e(__('messages.phone')).': '.e($person->phone).'<br>' : '')
                    .($person?->address ? e(__('messages.address')).': '.e($person->address) : '')
                    .'</p>
                </div>
                <div class="detail-box">
                    <h3>'.e(__('messages.cashbook')).'</h3>
                    <p class="info-line">'.e(__('messages.currency_unit')).': '.e($doc['currency']).'</p>
                    <p class="info-line">'.e(__('messages.cashbook_in_total')).': <span class="ltr">'.money_format($doc['total_in']).'</span></p>
                    <p class="info-line">'.e(__('messages.cashbook_out_total')).': <span class="ltr">'.money_format($doc['total_out']).'</span></p>
                </div>
            </div>
            <table>
                <thead><tr>
                    <th style="width:16%;">'.e(__('messages.date')).'</th>
                    <th style="width:44%;">'.e(__('messages.description')).'</th>
                    <th style="width:13%;">'.e(__('messages.cashbook_in_total')).'</th>
                    <th style="width:13%;">'.e(__('messages.cashbook_out_total')).'</th>
                    <th style="width:14%;">'.e(__('messages.balance')).'</th>
                </tr></thead>
                <tbody>'.$rows.'</tbody>
            </table>
            <div class="totals">
                <table class="totals-table">
                    <tr><td>'.e(__('messages.cashbook_in_total')).'</td>
                        <td class="ltr">'.money_format($doc['total_in']).' '.$this->rtlSpan($currencySymbol).'</td></tr>
                    <tr><td>'.e(__('messages.cashbook_out_total')).'</td>
                        <td class="ltr">'.money_format($doc['total_out']).' '.$this->rtlSpan($currencySymbol).'</td></tr>
                    <tr class="'.$closingClass.'"><td>'.e(__('messages.balance')).'</td>
                        <td class="ltr">'.$closingText.' '.$this->rtlSpan($currencySymbol).'</td></tr>
                </table>
            </div>
            <div class="footer">
                <p>'.e(__('messages.business_mgmt_system')).' (MGS) '.e(__('messages.built_by')).'</p>
                <p>'.e(__('messages.invoice_date')).': <span class="ltr">'.e($stamp).'</span></p>
            </div>';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => __('messages.paid'),
            'completed' => __('messages.completed'),
            'processing' => __('messages.processing'),
            'cancelled' => __('messages.cancelled'),
            'partial' => __('messages.partially_paid'),
            default => __('messages.pending'),
        };
    }

    protected function render(array $doc): string
    {
        $mpdf = $this->makeMpdf();
        $mpdf->SetTitle($doc['title'].' '.$doc['number']);
        $mpdf->SetDirectionality('rtl');

        $mpdf->WriteHTML($this->html($doc));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    protected function makeMpdf(): Mpdf
    {
        $tempDir = storage_path('app/private/mpdf');
        if (! is_dir($tempDir)) {
            Storage::disk('local')->makeDirectory('mpdf');
        }

        return new Mpdf([
            'tempDir' => $tempDir,
            'format' => 'A4',
            // Repo-managed fonts: vendor/ is wiped by composer install, and
            // LateefRegOT.ttf is not part of mpdf's own package.
            'fontDir' => [base_path('resources/fonts')],
            'fontdata' => [
                'lateef' => [
                    'R' => 'LateefRegOT.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 0.7,
                ],
                'dejavusans' => [
                    'R' => 'DejaVuSans.ttf',
                    'B' => 'DejaVuSans-Bold.ttf',
                ],
            ],
            'default_font' => 'lateef',
            'default_font_size' => 9,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);
    }

    /**
     * Mirrors resources/views/orders/print.blade.php's layout: header with
     * company + invoice badge, party/order boxes, items table, totals.
     */
    protected function html(array $doc): string
    {
        $company = $doc['company'];
        $currencySymbol = $doc['currency'] === 'USD' ? '$' : __('messages.afn');

        $contact = implode('', array_filter([
            ! empty($company['address']) ? e($company['address']).'<br>' : '',
            ! empty($company['phone']) ? e(__('messages.phone')).': '.e($company['phone']).'<br>' : '',
            ! empty($company['email']) ? e(__('messages.email')).': '.e($company['email']) : '',
        ]));

        $infoLines = '';
        foreach ($doc['info_lines'] as $line) {
            $infoLines .= '<p class="info-line">'.e($line).'</p>';
        }

        $rows = '';
        foreach ($doc['items'] as $item) {
            $lot = $item['lot']
                ? '<span class="lot">'.e(__('messages.lot_number')).': '.e($item['lot']).'</span>'
                : '';
            $rows .= '<tr>'
                .'<td>'.e($item['name']).$lot.'</td>'
                // Digits stay in the crisp .ltr cell; the unit name (often
                // Pashto) rides in its own shaped run next to them.
                .'<td class="ltr">'.e($item['qty']).($item['unit'] !== '' ? ' '.$this->rtlSpan($item['unit']) : '').'</td>'
                .'<td class="ltr">'.money_format($item['unit_price']).'</td>'
                .'<td class="ltr">'.money_format($item['subtotal']).'</td>'
                .'</tr>';
        }

        $totals = '';
        if ($doc['show_totals']) {
            $totals .= '<tr><td>'.e(__('messages.paid')).'</td>'
                .'<td class="ltr">'.money_format($doc['paid']).' '.$this->rtlSpan($currencySymbol).'</td></tr>'
                .'<tr><td>'.e(__('messages.current_pending')).'</td>'
                .'<td class="ltr">'.money_format($doc['pending']).' '.$this->rtlSpan($currencySymbol).'</td></tr>';
        }
        $totals .= '<tr class="total-row"><td>'.e(__('messages.total_amount')).'</td>'
            .'<td class="ltr">'.money_format($doc['total']).' '.$this->rtlSpan($currencySymbol).'</td></tr>';
        if ($doc['party_pending'] !== null) {
            $totals .= '<tr class="pending-row"><td>'.e($doc['party_pending_label']).'</td>'
                .'<td class="ltr">'.money_format($doc['party_pending']).' '.$this->rtlSpan($currencySymbol).'</td></tr>';
        }

        try {
            $stamp = Carbon::parse($doc['date'])->format('Y/m/d H:i');
        } catch (\Throwable) {
            $stamp = (string) $doc['date'];
        }

        $party = $doc['party'];

        return $this->style().'
            <div class="header">
                <div class="company-info">
                    <h1>'.e($company['name'] ?: 'MGS').'</h1>
                    <p>'.$contact.'</p>
                </div>
                <div class="invoice-badge">
                    <h2>'.e($doc['title']).'</h2>
                    <p>'.e(__('messages.number')).': <span class="ltr">'.e($doc['number']).'</span></p>
                    <p>'.e(__('messages.date')).': <span class="ltr">'.e($stamp).'</span></p>
                </div>
            </div>
            <div class="details-grid">
                <div class="detail-box">
                    <h3>'.e($doc['party_title']).'</h3>
                    <p><strong>'.e($party?->name ?? '-').'</strong><br>'
                    .($party?->phone ? e(__('messages.phone')).': '.e($party->phone).'<br>' : '')
                    .($party?->address ? e(__('messages.address')).': '.e($party->address) : '')
                    .'</p>
                </div>
                <div class="detail-box">
                    <h3>'.e($doc['info_title']).'</h3>
                    '.$infoLines.'
                </div>
            </div>
            <table>
                <thead><tr>
                    <th style="width:40%;">'.e(__('messages.items')).'</th>
                    <th style="width:15%;">'.e(__('messages.quantity')).'</th>
                    <th style="width:20%;">'.e(__('messages.unit_price')).'</th>
                    <th style="width:25%;">'.e(__('messages.total_line')).'</th>
                </tr></thead>
                <tbody>'.$rows.'</tbody>
            </table>
            <div class="totals">
                <table class="totals-table">'.$totals.'</table>
            </div>
            <div class="footer">
                <p>'.e(__('messages.business_mgmt_system')).' (MGS) '.e(__('messages.built_by')).'</p>
                <p>'.e(__('messages.invoice_date')).': <span class="ltr">'.e($stamp).'</span></p>
            </div>';
    }

    protected function style(): string
    {
        return '<style>
            body { font-family: lateef, dejavusans, sans-serif; color: '.self::INK.'; direction: rtl; font-size: 10pt; }
            .ltr { font-family: dejavusans, sans-serif; direction: ltr; unicode-bidi: embed; }
            .rtl-run { font-family: lateef, dejavusans, sans-serif; direction: rtl; unicode-bidi: embed; }
            /* Header band: the brand carries the document — white text on green. */
            .header { background: '.self::BRAND.'; border-radius: 3mm; padding: 5mm 6mm; margin-bottom: 6mm; color: #ffffff; }
            .company-info h1 { font-size: 17pt; color: #ffffff; margin: 0 0 1mm; }
            .company-info p { margin: 0; color: #d9f2e4; font-size: 9pt; }
            .invoice-badge { text-align: left; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.3); border-radius: 2mm; padding: 2.5mm 4mm; }
            .invoice-badge h2 { font-size: 14pt; color: #ffffff; margin: 0 0 1mm; }
            .invoice-badge p { margin: 0; font-size: 9pt; color: #eaf7ef; }
            .details-grid { margin-bottom: 5mm; }
            .detail-box { display: inline-block; width: 48%; vertical-align: top; background: #f2f8f4; border: 1px solid #dcece2; border-radius: 2mm; padding: 3mm; box-sizing: border-box; }
            .detail-box h3 { font-size: 10pt; color: #0a6d44; margin: 0 0 1.5mm; }
            .detail-box p { margin: 0; font-size: 9pt; line-height: 1.5; }
            .info-line { margin: 0 0 0.5mm; font-size: 9pt; }
            table { width: 100%; border-collapse: collapse; }
            /* Tinted head: dark green on pale green — crisp in print. */
            th { background: #e8f5ee; color: #0a6d44; font-size: 9pt; padding: 2mm; text-align: right; border-bottom: 1.5px solid '.self::BRAND.'; }
            td { font-size: 9pt; padding: 2mm; border-bottom: 1px solid #e8ede9; text-align: right; vertical-align: top; }
            tr:nth-child(even) td { background: #fafcfb; }
            span.lot { display: block; font-size: 7.5pt; color: #0c8c53; }
            .amount-in { color: #0c8c53; }
            .amount-out { color: #b45309; }
            .totals { margin-top: 4mm; width: 60%; margin-left: auto; }
            table.totals-table td { border-bottom: none; padding: 1mm 2mm; }
            /* Total band: the one focal point of the document. */
            .total-row td { background: '.self::BRAND.'; color: #ffffff; font-weight: bold; }
            .pending-row td { color: #b45309; }
            .footer { margin-top: 8mm; border-top: 1px solid #e5e7eb; padding-top: 2mm; color: #6b7280; font-size: 8pt; }
            .footer p { margin: 0 0 1mm; }
        </style>';
    }
}
