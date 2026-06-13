<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1f2937; font-size: 12px; }
        h1   { font-size: 22px; margin: 0 0 8px 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .right { text-align: right; }
        .totals td { border: none; padding: 4px 10px; }
    </style>
</head>
<body>
    <h1>A1 SMS Gateway</h1>
    <div class="muted">{{ config('app.url') }}</div>

    <div style="margin-top:24px; display:flex; justify-content:space-between;">
        <div>
            <strong>Bill to</strong><br>
            {{ $team->name }}<br>
            @if ($team->vat_number) VAT: {{ $team->vat_number }}<br> @endif
            @if ($team->country) {{ $team->country }} @endif
        </div>
        <div class="right">
            <strong>Invoice</strong><br>
            <span class="muted">{{ $invoice->number }}</span><br>
            Issued: {{ optional($invoice->issued_at)->toDateString() }}<br>
            Status: {{ ucfirst($invoice->status) }}
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->line_items as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="right">{{ $line['quantity'] }}</td>
                    <td class="right">{{ number_format($line['unit_amount'] / 100, 2) }}</td>
                    <td class="right">{{ number_format($line['amount'] / 100, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:16px; width:50%; margin-left:auto;">
        <tr><td>Subtotal</td><td class="right">{{ number_format($invoice->subtotal_ore / 100, 2) }} {{ $invoice->currency }}</td></tr>
        <tr><td>VAT</td><td class="right">{{ number_format($invoice->vat_ore / 100, 2) }} {{ $invoice->currency }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>{{ number_format($invoice->total_ore / 100, 2) }} {{ $invoice->currency }}</strong></td></tr>
    </table>

    <p class="muted" style="margin-top:48px; font-size: 11px;">
        A1 Tech Flow · sms.a1techflow.com · invoices generated automatically · keep this document for your records.
    </p>
</body>
</html>
