<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Render a shop's backup data as a printable PDF document.
 *
 * The JSON archive kept on disk is the machine-readable copy (future restore
 * support); this PDF is the shareable artifact the user downloads. mPDF with
 * Lateef (SIL OFL, bundled in vendor/mpdf/ttfonts) shapes the shop's
 * Dari/Pashto natively — DejaVu and every other stock font render Arabic
 * script as tofu boxes, and modern Noto Naskh builds carry OTL layout tables
 * mPDF 8.x cannot parse ("GPOS Lookup Type 5, Format 3"). Lateef's tables
 * parse cleanly and its GSUB joining was verified through mPDF's Otl::applyOTL.
 */
class BackupPdfService
{
    protected const BRAND = '#10ae64';

    protected const INK = '#1f2328';

    protected const MAX_ROWS = 500;

    public function generate(array $data): string
    {
        $mpdf = new Mpdf([
            'tempDir' => storage_path('app/private/mpdf'),
            'format' => 'A4',
            // Lateef covers the Arabic block incl. the Pashto letters (ګ ۍ څ ځ ږ)
            // plus Latin, so money/date spans don't even need a second font —
            // .ltr { font-family: dejavusans } stays for crisper Latin digits.
            'fontDir' => [__DIR__.'/../../../vendor/mpdf/mpdf/ttfonts'],
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
        $mpdf->SetTitle('Backup — '.now()->format('Y/m/d H:i'));
        $mpdf->SetDirectionality('rtl');

        $html = $this->coverHtml($data);

        foreach ($this->sections($data) as $section) {
            $rows = $section['rows']($data);
            if (! $rows) {
                continue;
            }
            $html .= $this->table($section['title'], $section['headers'], $rows);
        }

        $html .= $this->html('
            <p class="footer-note">'.e(__('messages.backup_instruction')).'</p>
        ');

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    protected function coverHtml(array $data): string
    {
        $company = $this->settingValue($data, 'company_name') ?: 'MGS';
        $stamp = now()->format('Y/m/d H:i');

        $counts = '';
        foreach ($this->countSummary($data) as $label => $n) {
            if ($n === 0) {
                continue;
            }
            $counts .= '<tr><td class="count-label">'.e($label).'</td>'
                .'<td class="count-value ltr" dir="ltr">'.number_format($n).'</td></tr>';
        }

        return $this->html('
            <h1 class="brand-title">'.e($company).' — '.e(__('messages.backup')).'</h1>
            <p class="brand-sub">'.e(__('messages.date')).': <span class="ltr" dir="ltr">'.e($stamp).'</span></p>
            <table class="count-table">'.$counts.'</table>
        ');
    }

    protected function countSummary(array $data): array
    {
        return [
            __('messages.customers') => count($data['customers'] ?? []),
            __('messages.suppliers') => count($data['suppliers'] ?? []),
            __('messages.products') => count($data['products'] ?? []),
            __('messages.orders') => count($data['orders'] ?? []),
            __('messages.purchases') => count($data['purchases'] ?? []),
            __('messages.expenses') => count($data['expenses'] ?? []),
            __('messages.staff') => count($data['employees'] ?? []),
            // Both cashbook generations, matching what the controller gathers.
            __('messages.cashbook_section') => count($data['cashbook_entries'] ?? [])
                + count($this->cashbookJournals($data)),
        ];
    }

    /**
     * Ordered sections. Each lazily maps the raw backup arrays into display rows
     * so the renderer never touches the database.
     */
    protected function sections(array $data): array
    {
        return [
            [
                'title' => __('messages.customers'),
                'headers' => [__('messages.name'), __('messages.phone'), __('messages.address'), __('messages.date')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [$r['name'], $this->txt($r['phone'] ?? ''), $r['address'], $this->date($r['created_at'])],
                    array_slice($d['customers'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.suppliers'),
                'headers' => [__('messages.name'), __('messages.phone'), __('messages.address'), __('messages.date')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [$r['name'], $this->txt($r['phone'] ?? ''), $r['address'], $this->date($r['created_at'])],
                    array_slice($d['suppliers'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.products'),
                'headers' => [__('messages.name'), __('messages.category'), __('messages.price'), __('messages.price').' ('.__('messages.usd').')', __('messages.stock'), __('messages.barcode')],
                'rows' => function ($d) {
                    $cats = $this->indexBy($d['categories'] ?? []);
                    $units = $this->indexBy($d['units'] ?? []);

                    return array_map(function ($r) use ($cats, $units) {
                        $category = trim(($cats[$r['category_id']]['name'] ?? '').' · '.($units[$r['unit_id']]['short_name'] ?? $units[$r['unit_id']]['name'] ?? ''), ' ·');

                        return [
                            $r['name'],
                            $category,
                            $this->money($r['price'] ?? 0, 'AFN'),
                            $this->money($r['price_usd'] ?? null, 'USD'),
                            $this->num($r['stock'] ?? 0),
                            $this->txt($r['barcode'] ?? ''),
                        ];
                    }, array_slice($d['products'] ?? [], 0, self::MAX_ROWS, true));
                },
            ],
            [
                'title' => __('messages.orders'),
                'headers' => ['#', __('messages.customer'), __('messages.status'), __('messages.total'), __('messages.date')],
                'rows' => function ($d) {
                    $people = $this->partyNames($d);

                    return array_map(fn ($r) => [
                        $this->txt('#'.$r['id']),
                        $people[$r['person_type'] ?? 'customer'][$r['person_id']] ?? $this->txt('#'.($r['person_id'] ?? '')),
                        $r['status'],
                        $this->money($r['total_amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $this->date($r['created_at']),
                    ], array_slice($d['orders'] ?? [], 0, self::MAX_ROWS, true));
                },
            ],
            [
                'title' => __('messages.order_items'),
                'headers' => [__('messages.order'), __('messages.product'), __('messages.quantity'), __('messages.unit_price'), __('messages.subtotal')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $this->txt('#'.$r['order_id']),
                        $r['product_name'] ?? $this->txt('#'.($r['product_id'] ?? '')),
                        $this->num($r['quantity'] ?? 0),
                        $this->money($r['unit_price'] ?? 0, $r['order_currency'] ?? 'AFN'),
                        $this->money($r['subtotal'] ?? 0, $r['order_currency'] ?? 'AFN'),
                    ],
                    array_slice($d['order_items'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.payments'),
                'headers' => [__('messages.order'), __('messages.amount'), __('messages.notes'), __('messages.date')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $this->txt('#'.$r['order_id']),
                        $this->money($r['amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $r['notes'] ?? '',
                        $this->date($r['created_at']),
                    ],
                    array_slice($d['payments'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.order_returns'),
                'headers' => [__('messages.order'), __('messages.total'), __('messages.status'), __('messages.reason'), __('messages.date')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $this->txt('#'.$r['order_id']),
                        $this->money($r['total_amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $r['status'] ?? '',
                        $r['reason'] ?? '',
                        $this->date($r['created_at']),
                    ],
                    array_slice($d['order_returns'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.purchases'),
                'headers' => ['#', __('messages.supplier'), __('messages.status'), __('messages.total'), __('messages.date')],
                'rows' => function ($d) {
                    $people = $this->partyNames($d);

                    return array_map(fn ($r) => [
                        $this->txt('#'.$r['id']),
                        $people[$r['person_type'] ?? 'supplier'][$r['person_id']] ?? $this->txt('#'.($r['person_id'] ?? '')),
                        $r['status'],
                        $this->money($r['total_amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $this->date($r['created_at']),
                    ], array_slice($d['purchases'] ?? [], 0, self::MAX_ROWS, true));
                },
            ],
            [
                'title' => __('messages.purchase_items'),
                'headers' => [__('messages.purchase'), __('messages.product'), __('messages.quantity'), __('messages.unit_price'), __('messages.subtotal')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $this->txt('#'.$r['purchase_id']),
                        $r['product_name'] ?? $this->txt('#'.($r['product_id'] ?? '')),
                        $this->num($r['quantity'] ?? 0),
                        $this->money($r['unit_price'] ?? 0, $r['purchase_currency'] ?? 'AFN'),
                        $this->money($r['subtotal'] ?? 0, $r['purchase_currency'] ?? 'AFN'),
                    ],
                    array_slice($d['purchase_items'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.purchase_payments'),
                'headers' => [__('messages.purchase'), __('messages.amount'), __('messages.notes'), __('messages.date')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $this->txt('#'.$r['purchase_id']),
                        $this->money($r['amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $r['notes'] ?? '',
                        $this->date($r['created_at']),
                    ],
                    array_slice($d['purchase_payments'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.purchase_returns'),
                'headers' => [__('messages.purchase'), __('messages.total'), __('messages.status'), __('messages.reason'), __('messages.date')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $this->txt('#'.$r['purchase_id']),
                        $this->money($r['total_amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $r['status'] ?? '',
                        $r['reason'] ?? '',
                        $this->date($r['created_at']),
                    ],
                    array_slice($d['purchase_returns'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.ledger'),
                'headers' => [__('messages.name'), __('messages.type'), __('messages.amount'), __('messages.notes'), __('messages.date')],
                'rows' => function ($d) {
                    $people = $this->partyNames($d);

                    return array_map(fn ($r) => [
                        $people[$r['person_type'] ?? ''][$r['person_id']] ?? '',
                        $r['type'] === 'payment_received' ? __('messages.paid_short') : $r['type'],
                        $this->money($r['amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $r['notes'] ?? '',
                        $this->date($r['created_at']),
                    ], array_slice($d['party_payments'] ?? [], 0, self::MAX_ROWS, true));
                },
            ],
            [
                'title' => __('messages.expenses'),
                'headers' => [__('messages.category'), __('messages.amount'), __('messages.expense_date'), __('messages.notes')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $r['category'] ?? '',
                        $this->money($r['amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $this->date($r['expense_date'] ?? $r['created_at']),
                        $r['notes'] ?? '',
                    ],
                    array_slice($d['expenses'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.staff'),
                'headers' => [__('messages.name'), __('messages.phone'), __('messages.position'), __('messages.monthly_salary')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $r['name'],
                        $this->txt($r['phone'] ?? ''),
                        $r['position'] ?? '',
                        $this->money($r['monthly_salary'] ?? 0, $r['currency'] ?? 'AFN'),
                    ],
                    array_slice($d['employees'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.salaries'),
                'headers' => [__('messages.name'), __('messages.for_month'), __('messages.amount'), __('messages.notes')],
                'rows' => function ($d) {
                    $staff = $this->indexBy($d['employees'] ?? []);

                    return array_map(fn ($r) => [
                        $staff[$r['employee_id']]['name'] ?? $this->txt('#'.($r['employee_id'] ?? '')),
                        $this->date($r['for_month'] ?? '', 'Y/m'),
                        $this->money($r['amount'] ?? 0, $r['currency'] ?? 'AFN'),
                        $r['notes'] ?? '',
                    ], array_slice($d['salary_payments'] ?? [], 0, self::MAX_ROWS, true));
                },
            ],
            [
                'title' => __('messages.cashbook_section'),
                'headers' => [__('messages.type'), __('messages.category'), __('messages.amount'), __('messages.date'), __('messages.notes')],
                'rows' => fn ($d) => $this->mergedCashbookRows($d),
            ],
            [
                'title' => __('messages.accounts'),
                'headers' => [__('messages.name'), __('messages.type'), __('messages.phone'), __('messages.currency')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $r['name'],
                        $r['type'],
                        $this->txt($r['phone'] ?? ''),
                        $this->txt($r['currency'] ?? ''),
                    ],
                    array_slice($d['accounts'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.journal_entries'),
                'headers' => [__('messages.description'), __('messages.date'), __('messages.currency'), __('messages.source')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [
                        $r['description'] ?? '',
                        $this->date($r['transaction_date'] ?? $r['created_at']),
                        $this->txt($r['currency'] ?? ''),
                        $r['source'] ?? '',
                    ],
                    array_slice($d['journal_entries'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
            [
                'title' => __('messages.stock_movements'),
                'headers' => [__('messages.product'), __('messages.quantity'), __('messages.type'), __('messages.date'), __('messages.notes')],
                'rows' => function ($d) {
                    $products = $this->indexBy($d['products'] ?? [], 'name', 'name');

                    return array_map(fn ($r) => [
                        $products[$r['product_id']] ?? $this->txt('#'.($r['product_id'] ?? '')),
                        $this->num($r['quantity_change'] ?? 0),
                        $r['movement_type'],
                        $this->date($r['created_at']),
                        $r['notes'] ?? '',
                    ], array_slice($d['stock_movements'] ?? [], 0, self::MAX_ROWS, true));
                },
            ],
            [
                'title' => __('messages.settings'),
                'headers' => [__('messages.key'), __('messages.value')],
                'rows' => fn ($d) => array_map(
                    fn ($r) => [$r['key'], $this->isSecret($r['key']) ? '••••••••' : (string) ($r['value'] ?? '')],
                    array_slice($d['settings'] ?? [], 0, self::MAX_ROWS, true)
                ),
            ],
        ];
    }

    protected function partyNames(array $data): array
    {
        // id => name strings: sections embed these directly as table cells,
        // so full row arrays here would hit "Array to string conversion".
        return [
            'customer' => $this->indexBy($data['customers'] ?? [], 'name', 'name'),
            'supplier' => $this->indexBy($data['suppliers'] ?? [], 'name', 'name'),
        ];
    }

    /**
     * Journal-based cashbook entries (the only write path since the cashbook
     * redesign): rows from the backup arrays only, never the database.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function cashbookJournals(array $data): array
    {
        $lines = [];
        foreach ($data['ledger_entries'] ?? [] as $line) {
            $lines[$line['journal_entry_id']][] = $line;
        }

        $journals = [];
        foreach ($data['journal_entries'] ?? [] as $journal) {
            if (! in_array($journal['source'] ?? '', ['cashbook_in', 'cashbook_out'], true)) {
                continue;
            }

            // A journal posts two balanced lines (debit + credit, equal
            // amounts); the entry's amount is the shared MAX — the same
            // read CashbookController makes. Notes live on the line that
            // carries them (the cash side).
            $journalLines = $lines[$journal['id']] ?? [];
            $amount = '0.00';
            $notes = '';
            foreach ($journalLines as $line) {
                if (bccomp((string) ($line['amount'] ?? '0'), $amount, 2) === 1) {
                    $amount = (string) $line['amount'];
                }
                if (($line['notes'] ?? '') !== '') {
                    $notes = (string) $line['notes'];
                }
            }

            $journal['_cashbook_amount'] = $amount;
            $journal['_cashbook_notes'] = $notes;
            $journals[] = $journal;
        }

        return $journals;
    }

    /**
     * One merged cashbook table: legacy cashbook_entries rows plus the
     * journal-based entries above, newest first, capped at MAX_ROWS.
     */
    protected function mergedCashbookRows(array $data): array
    {
        $people = $this->partyNames($data);

        $legacy = array_map(fn ($r) => [
            'type' => $r['type'],
            'category' => $r['category'] ?? '',
            'amount' => $this->money($r['amount'] ?? 0, $r['currency'] ?? 'AFN'),
            'date' => $this->date($r['entry_date'] ?? $r['created_at'] ?? null),
            'notes' => $r['notes'] ?? '',
            'sort' => $r['entry_date'] ?? ($r['created_at'] ?? ''),
        ], $data['cashbook_entries'] ?? []);

        $journalRows = array_map(function ($r) use ($people) {
            $category = $r['description'] ?? '';
            if (! empty($r['reference_type'])) {
                $party = $people[$r['reference_type'] ?? ''][$r['reference_id'] ?? 0] ?? null;
                if ($party !== null) {
                    $category .= ' · '.$party;
                }
            }

            return [
                'type' => $r['source'] === 'cashbook_in' ? 'in' : 'out',
                'category' => $category,
                'amount' => $this->money($r['_cashbook_amount'] ?? 0, $r['currency'] ?? 'AFN'),
                'date' => $this->date($r['transaction_date'] ?? ($r['created_at'] ?? null)),
                'notes' => $r['_cashbook_notes'] ?? '',
                'sort' => $r['transaction_date'] ?? ($r['created_at'] ?? ''),
            ];
        }, $this->cashbookJournals($data));

        $merged = array_merge($legacy, $journalRows);
        usort($merged, fn ($a, $b) => strcmp((string) $b['sort'], (string) $a['sort']));

        return array_map(
            fn ($r) => [$r['type'], $r['category'], $r['amount'], $r['date'], $r['notes']],
            array_slice($merged, 0, self::MAX_ROWS)
        );
    }

    protected function indexBy(array $rows, string $valueKey = 'name', ?string $only = null): array
    {
        $index = [];
        foreach ($rows as $row) {
            $index[$row['id']] = $only !== null
                ? (string) ($row[$only] ?? '')
                : $row + ($valueKey === 'name' ? [] : [$valueKey => $row[$valueKey] ?? '']);
        }

        return $index;
    }

    protected function settingValue(array $data, string $key): ?string
    {
        foreach ($data['settings'] ?? [] as $row) {
            if (($row['key'] ?? null) === $key) {
                return $row['value'];
            }
        }

        return null;
    }

    protected function isSecret(string $key): bool
    {
        return (bool) preg_match('/(api_key|token|password|secret)/i', $key);
    }

    protected function table(string $title, array $headers, array $rows): string
    {
        $th = '';
        foreach ($headers as $h) {
            $th .= '<th>'.e($h).'</th>';
        }

        $tr = '';
        $limited = count($rows) >= self::MAX_ROWS;
        foreach ($rows as $cells) {
            $td = '';
            foreach ($cells as $cell) {
                // PdfCell carries HTML this service built itself (money/date
                // spans); everything else is user data and stays escaped.
                $td .= '<td>'.($cell instanceof PdfCell ? (string) $cell : e((string) ($cell ?? ''))).'</td>';
            }
            $tr .= '<tr>'.$td.'</tr>';
        }

        $note = $limited
            ? '<p class="limit-note">'.e(__('messages.backup_limit_note', ['max' => self::MAX_ROWS])).'</p>'
            : '';

        return '
            <h2 class="section-title">'.e($title).'</h2>
            <table class="data-table"><thead><tr>'.$th.'</tr></thead><tbody>'.$tr.'</tbody></table>'.$note;
    }

    /**
     * Quantities: grouped thousands are fine here (50 -> "50", 1200 -> "1,200").
     */
    protected function num(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return is_numeric($v) ? number_format((float) $v) : (string) $v;
    }

    /**
     * Identifiers: phones, barcodes, "#12" refs. Never grouped, never
     * zero-stripped — number_format() would turn "0700268836" into
     * "700,268,836" and mangle every phone in the document.
     */
    protected function txt(mixed $v): string
    {
        return (string) ($v ?? '');
    }

    protected function money(mixed $v, ?string $currency): PdfCell
    {
        if ($v === null || $v === '') {
            return new PdfCell('');
        }

        $symbol = $currency === 'USD' ? '$' : __('messages.afn');

        return new PdfCell('<span dir="ltr">'.number_format((float) $v, 2).' '.e($symbol).'</span>');
    }

    protected function date(mixed $v, string $format = 'Y/m/d'): PdfCell|string
    {
        if (empty($v)) {
            return '';
        }

        try {
            return new PdfCell('<span dir="ltr">'.Carbon::parse($v)->format($format).'</span>');
        } catch (\Throwable) {
            return (string) $v;
        }
    }

    protected function html(string $body): string
    {
        return '<style>
            body { font-family: lateef, dejavusans, sans-serif; color: '.self::INK.'; direction: rtl; }
            /* Latin runs (money, dates, ids): Lateef Latin glyphs are thin;
               DejaVu renders digits and codes crisper. */
            .ltr { font-family: dejavusans, sans-serif; }
            h1.brand-title { font-size: 18pt; color: '.self::BRAND.'; margin: 0 0 2mm; }
            p.brand-sub { color: #6b7280; font-size: 8.5pt; margin: 0 0 5mm; }
            table.count-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
            table.count-table td { padding: 1.2mm 2mm; font-size: 9pt; border-bottom: 1px solid #e5e7eb; }
            td.count-label { color: #374151; font-weight: bold; }
            td.count-value { width: 30mm; font-weight: bold; color: '.self::BRAND.'; }
            h2.section-title { font-size: 11pt; color: '.self::BRAND.'; border-bottom: 1.5px solid '.self::BRAND.'; padding-bottom: 1mm; margin: 5mm 0 2mm; }
            table.data-table { width: 100%; border-collapse: collapse; }
            table.data-table th { background: '.self::BRAND.'; color: #ffffff; font-size: 8pt; padding: 1.5mm 2mm; text-align: right; }
            table.data-table td { font-size: 8pt; padding: 1.2mm 2mm; border-bottom: 1px solid #e5e7eb; text-align: right; }
            table.data-table tr:nth-child(even) td { background: #f9fafb; }
            p.limit-note { color: #9ca3af; font-size: 7.5pt; margin: 1mm 0 0; }
            p.footer-note { color: #6b7280; font-size: 8pt; margin-top: 6mm; border-top: 1px solid #e5e7eb; padding-top: 2mm; }
        </style>'.$body;
    }
}
