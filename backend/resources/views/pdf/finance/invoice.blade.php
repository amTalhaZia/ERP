<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2933; }
        .muted { color: #6b7785; }
        .header { width: 100%; border-bottom: 2px solid #1f4e79; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .logo { max-height: 56px; max-width: 180px; margin-bottom: 6px; }
        .company-name { font-size: 16px; font-weight: bold; color: #1f4e79; }
        .doc-title { font-size: 22px; font-weight: bold; text-align: right; color: #1f4e79; letter-spacing: 1px; }
        .meta { width: 100%; margin-bottom: 18px; }
        .meta td { vertical-align: top; width: 50%; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7785; letter-spacing: .5px; }
        table.facts td { padding: 2px 0 2px 12px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.lines th { background: #1f4e79; color: #fff; font-weight: bold; padding: 6px; text-align: left; font-size: 9.5px; }
        table.lines td { padding: 6px; border-bottom: 1px solid #e3e8ee; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 42%; margin-left: 58%; border-collapse: collapse; }
        table.totals td { padding: 4px 6px; }
        table.totals tr.grand td { border-top: 2px solid #1f4e79; font-weight: bold; font-size: 12px; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 3px; background: #e3e8ee; font-size: 9px; text-transform: uppercase; }
        .notes { margin-top: 22px; }
        .notes p { margin: 2px 0 10px; white-space: pre-line; }
    </style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) (string) $value, 2, '.', ',');
@endphp
<table class="header">
    <tr>
        <td>
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
                <br>
            @endif
            <span class="company-name">{{ $company?->legal_name ?: $company?->name }}</span><br>
            <span class="muted">
                @foreach (array_filter([$company?->address_line1, $company?->address_line2, trim(($company?->city ?? '').' '.($company?->postal_code ?? '')), $company?->country]) as $part)
                    {{ $part }}<br>
                @endforeach
                @if ($company?->tax_number) Tax no. {{ $company->tax_number }}<br> @endif
                {{ $company?->email }} {{ $company?->phone }}
            </span>
        </td>
        <td>
            <div class="doc-title">INVOICE</div>
            <div style="text-align: right; margin-top: 6px;">
                <strong>{{ $invoice->number }}</strong><br>
                <span class="status">{{ str_replace('_', ' ', $invoice->status->value) }}</span>
            </div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td>
            <div class="label">Bill to</div>
            <strong>{{ $customer['name'] ?? ('Customer #'.$invoice->customer_id) }}</strong><br>
            <span class="muted">
                @foreach (array_filter([$customer['billing_address_line1'] ?? null, $customer['billing_address_line2'] ?? null, trim(($customer['billing_city'] ?? '').' '.($customer['billing_postal_code'] ?? '')), $customer['billing_country'] ?? null]) as $part)
                    {{ $part }}<br>
                @endforeach
                @if (! empty($customer['tax_number'])) Tax no. {{ $customer['tax_number'] }}<br> @endif
                {{ $customer['email'] ?? '' }}
            </span>
        </td>
        <td>
            <table class="facts" style="margin-left: auto;">
                <tr><td class="label">Invoice date</td><td>{{ $invoice->invoice_date->toDateString() }}</td></tr>
                <tr><td class="label">Due date</td><td>{{ $invoice->due_date->toDateString() }}</td></tr>
                <tr><td class="label">Currency</td><td>{{ $invoice->currency }}</td></tr>
                <tr><td class="label">Balance due</td><td><strong>{{ $money($invoice->balance_due) }}</strong></td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th style="width: 4%;">#</th>
        <th>Description</th>
        <th class="num">Qty</th>
        <th class="num">Unit price</th>
        <th class="num">Disc. %</th>
        <th class="num">Tax %</th>
        <th class="num">Amount</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($invoice->lines as $line)
        <tr>
            <td>{{ $line->line_no }}</td>
            <td>{{ $line->description }}</td>
            <td class="num">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
            <td class="num">{{ $money($line->unit_price) }}</td>
            <td class="num">{{ rtrim(rtrim((string) $line->discount_percent, '0'), '.') ?: '0' }}</td>
            <td class="num">{{ rtrim(rtrim((string) $line->tax_rate, '0'), '.') ?: '0' }}</td>
            <td class="num">{{ $money($line->line_subtotal) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
    @if ((float) (string) $invoice->discount_total > 0)
        <tr><td class="muted">Discounts included</td><td class="num muted">{{ $money($invoice->discount_total) }}</td></tr>
    @endif
    <tr><td>Tax</td><td class="num">{{ $money($invoice->tax_total) }}</td></tr>
    <tr class="grand"><td>Total {{ $invoice->currency }}</td><td class="num">{{ $money($invoice->total) }}</td></tr>
    @if ((float) (string) $invoice->amount_paid > 0)
        <tr><td>Paid</td><td class="num">{{ $money($invoice->amount_paid) }}</td></tr>
        <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ $money($invoice->balance_due) }}</strong></td></tr>
    @endif
</table>

@if ($invoice->notes || $invoice->terms)
    <div class="notes">
        @if ($invoice->notes)
            <div class="label">Notes</div>
            <p>{{ $invoice->notes }}</p>
        @endif
        @if ($invoice->terms)
            <div class="label">Terms</div>
            <p>{{ $invoice->terms }}</p>
        @endif
    </div>
@endif
</body>
</html>
