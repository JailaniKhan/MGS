{{-- Shared stylesheet for standalone invoice / purchase-bill print pages.
     Standalone documents: no Tailwind; brand hexes mirror app.css tokens
     (--color-brand #10ae64 · --color-primary-600 #0c8c53 · -700 #0a6d44). --}}
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
        border-bottom: 2px solid #10ae64;
        padding-bottom: 20px;
        margin-bottom: 20px;
    }
    .company-info h1 {
        font-size: 22px;
        color: #0c8c53;
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
        color: #0c8c53;
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
        color: #0c8c53;
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
        background: #0c8c53;
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
        background: #0c8c53;
        color: #fff;
        font-size: 15px;
    }
    .totals-table .total-row td {
        border-bottom: none;
        padding: 12px;
    }
    .totals-table .pending-row {
        background: #f0fdf4;
        font-size: 14px;
        font-weight: 600;
        color: #0a6d44;
    }
    .totals-table .pending-row td {
        border-bottom: 1px solid #bbf7d0;
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
        .invoice { border: none; max-width: 100%; }
        .actions { display: none; }
        th { color: #000; background: #e5e7eb; }
        .invoice-badge { float: left; }
    }
</style>
