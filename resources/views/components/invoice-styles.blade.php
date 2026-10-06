{{-- Shared stylesheet for standalone invoice / purchase-bill print pages.
     Standalone documents: no Tailwind; brand hexes mirror app.css tokens
     (--color-brand #10ae64 · --color-primary-600 #0c8c53 · -700 #0a6d44). --}}
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        direction: rtl;
        padding: 20px;
        color: #242426;
        background: #f4f6f3;
    }
    .invoice {
        max-width: 800px;
        margin: 0 auto;
        border-radius: 14px;
        padding: 30px;
        background: #fff;
        box-shadow: 0 18px 40px -24px rgba(10, 109, 68, 0.25), 0 2px 6px -2px rgba(20, 20, 21, 0.06);
    }
    /* Header band: the brand carries the document — company on the right,
       the invoice badge on the left, both reading white on green. */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        background: linear-gradient(135deg, #0c8c53 0%, #10ae64 100%);
        border-radius: 10px;
        padding: 20px 24px;
        margin-bottom: 24px;
        color: #fff;
    }
    .company-info h1 {
        font-size: 24px;
        font-weight: 700;
        color: #fff;
        letter-spacing: -0.02em;
        margin-bottom: 6px;
    }
    .company-info p {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.85);
        line-height: 1.8;
    }
    .invoice-badge {
        text-align: left;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 8px;
        padding: 10px 16px;
    }
    .invoice-badge h2 {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        letter-spacing: -0.01em;
    }
    .invoice-badge p {
        font-size: 12.5px;
        color: rgba(255, 255, 255, 0.9);
        margin-top: 4px;
    }
    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 24px;
    }
    .detail-box {
        border-radius: 8px;
        padding: 14px 16px;
        background: #f2f8f4;
        border: 1px solid #dcece2;
    }
    .detail-box h3 {
        font-size: 13px;
        font-weight: 700;
        color: #0a6d44;
        margin-bottom: 8px;
        letter-spacing: 0.02em;
    }
    .detail-box p {
        font-size: 13px;
        color: #3a3a3c;
        line-height: 1.8;
    }
    .detail-box p strong {
        color: #242426;
    }
    .detail-box p span {
        color: #6b7280;
        font-size: 12px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
    }
    /* Tinted head: dark green text on pale green — modern, and it prints
       without flooding the page in ink. */
    th {
        background: #e8f5ee;
        color: #0a6d44;
        padding: 11px 12px;
        font-size: 12.5px;
        font-weight: 700;
        text-align: right;
        border-bottom: 2px solid #10ae64;
    }
    td {
        padding: 11px 12px;
        font-size: 13px;
        border-bottom: 1px solid #eef2ee;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    tr:nth-child(even) td {
        background: #fafcfb;
    }
    td.qty, td.price, td.subtotal {
        text-align: center;
    }
    td.subtotal {
        font-weight: 600;
    }
    .lot {
        font-size: 11px;
        color: #0c8c53;
        font-weight: 600;
    }
    .note {
        font-size: 11px;
        color: #6b7280;
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
        padding: 9px 12px;
        font-size: 13px;
        border-bottom: 1px solid #eef2ee;
        font-variant-numeric: tabular-nums;
    }
    .totals-table td:last-child {
        text-align: left;
        font-weight: 600;
    }
    /* Total band: the one focal point of the document. */
    .totals-table .total-row td {
        background: linear-gradient(135deg, #0c8c53 0%, #10ae64 100%);
        color: #fff;
        font-size: 15px;
        border-bottom: none;
    }
    .totals-table .total-row td:first-child {
        border-radius: 0 8px 8px 0;
    }
    .totals-table .total-row td:last-child {
        border-radius: 8px 0 0 8px;
    }
    .totals-table .pending-row td {
        background: #f0fdf4;
        font-size: 14px;
        font-weight: 600;
        color: #0a6d44;
        border-bottom: 1px solid #bbf7d0;
    }
    .footer {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px dashed #d5d5cf;
        text-align: center;
        font-size: 12px;
        color: #8a8a86;
    }
    /* Action bar: browser print dialog, or the device bridge bar. */
    .actions {
        margin-top: 28px;
    }
    .actions-inner {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .actions-inner form {
        display: flex;
        margin: 0;
    }
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: 12px;
        border: 1px solid transparent;
        font-family: inherit;
        font-size: 13.5px;
        font-weight: 600;
        letter-spacing: -0.01em;
        cursor: pointer;
        background: #0c8c53;
        color: #fff;
        box-shadow: 0 6px 16px -6px rgba(16, 174, 100, 0.55);
        transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .action-btn svg {
        width: 17px;
        height: 17px;
        flex-shrink: 0;
    }
    .action-btn:hover {
        background: #0a7a49;
        box-shadow: 0 10px 22px -8px rgba(16, 174, 100, 0.6);
    }
    .action-btn:active {
        transform: translateY(1px) scale(0.99);
    }
    .action-btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px #fff, 0 0 0 4px #10ae64;
    }
    /* Secondary device actions: quiet tinted siblings of Print. */
    .action-btn-quiet {
        background: #f2f8f4;
        color: #0a6d44;
        border-color: #dcece2;
        box-shadow: none;
    }
    .action-btn-quiet:hover {
        background: #e8f5ee;
        box-shadow: none;
    }
    /* Device print-action result notice (x-print-flash): a bottom
       snack-bar so it never fights the document preview. */
    .print-flash {
        position: fixed;
        inset-inline: 14px;
        bottom: 16px;
        z-index: 50;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 480px;
        margin-inline: auto;
        padding: 12px 16px;
        border-radius: 12px;
        background: #0c8c53;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 14px 32px -12px rgba(10, 109, 68, 0.55);
        animation: printFlashIn 0.3s ease both;
    }
    .print-flash-error {
        background: #b91c1c;
        box-shadow: 0 14px 32px -12px rgba(185, 28, 28, 0.55);
    }
    .print-flash svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
    .print-flash span {
        flex: 1;
    }
    .print-flash-close {
        flex-shrink: 0;
        display: flex;
        background: none;
        border: 0;
        padding: 4px;
        color: rgba(255, 255, 255, 0.85);
        cursor: pointer;
    }
    .print-flash-close svg {
        width: 14px;
        height: 14px;
    }
    @keyframes printFlashIn {
        from {
            opacity: 0;
            transform: translateY(14px);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }
    /* Phone layout: the invoice fills the screen as a pure preview. */
    @media (max-width: 640px) {
        body {
            padding: 10px;
        }
        .invoice {
            padding: 18px;
            border-radius: 12px;
        }
        .header {
            flex-direction: column;
            gap: 12px;
            padding: 16px;
        }
        .invoice-badge {
            text-align: right;
            width: 100%;
        }
        .details-grid {
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        th, td {
            padding: 8px;
            font-size: 11.5px;
        }
        .totals {
            justify-content: stretch;
            margin-bottom: 12px;
        }
        .totals-table {
            width: 100%;
        }
        /* Device action bar: the three actions share the width and wrap
           onto a second row instead of shrinking under their labels. */
        .action-btn {
            flex: 1 1 140px;
            padding: 12px 16px;
        }
    }
    @media print {
        body { padding: 0; background: #fff; }
        .invoice { box-shadow: none; border-radius: 0; max-width: 100%; }
        .header { border-radius: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .actions { display: none; }
        .print-flash { display: none; }
        .preview-note { display: none; }
        th { color: #000; background: #e5e7eb; border-bottom: 2px solid #9ca3af; }
        .totals-table .total-row td {
            background: #0c8c53 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .invoice-badge { float: left; }
    }
</style>
