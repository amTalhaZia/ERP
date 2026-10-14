<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payslip->number }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2933; }
        .muted { color: #6b7785; }
        .header { width: 100%; border-bottom: 2px solid #1f4e79; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .logo { max-height: 56px; max-width: 180px; margin-bottom: 6px; }
        .company-name { font-size: 16px; font-weight: bold; color: #1f4e79; }
        .doc-title { font-size: 22px; font-weight: bold; text-align: right; color: #1f4e79; letter-spacing: 1px; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7785; letter-spacing: .5px; }
        table.facts { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        table.facts td { padding: 3px 6px; vertical-align: top; width: 25%; }
        .columns { width: 100%; border-collapse: collapse; }
        .columns > tbody > tr > td { vertical-align: top; width: 50%; padding: 0 6px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.lines th { background: #1f4e79; color: #fff; font-weight: bold; padding: 6px; text-align: left; font-size: 9.5px; }
        table.lines td { padding: 5px 6px; border-bottom: 1px solid #e3e8ee; }
        table.lines tr.total td { font-weight: bold; border-top: 2px solid #1f4e79; }
        .num { text-align: right; white-space: nowrap; }
        .net { margin-top: 10px; width: 100%; border-collapse: collapse; }
        .net td { padding: 8px; background: #eef3f8; font-size: 13px; font-weight: bold; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 3px; background: #e3e8ee; font-size: 9px; text-transform: uppercase; }
        .footer { margin-top: 24px; font-size: 8.5px; color: #6b7785; }
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
            </span>
        </td>
        <td>
            <div class="doc-title">PAYSLIP</div>
            <div style="text-align: right; margin-top: 6px;">
                <strong>{{ $payslip->number ?? 'Draft' }}</strong><br>
                <span class="muted">{{ $run->period_start->toDateString() }} – {{ $run->period_end->toDateString() }}</span><br>
                <span class="status">{{ $payslip->status->value }}</span>
            </div>
        </td>
    </tr>
</table>

<table class="facts">
    <tr>
        <td><div class="label">Employee</div>{{ $payslip->employee_name }}</td>
        <td><div class="label">Employee code</div>{{ $payslip->employee_code }}</td>
        <td><div class="label">Department</div>{{ $payslip->department_name ?? '—' }}</td>
        <td><div class="label">Designation</div>{{ $payslip->designation_title ?? '—' }}</td>
    </tr>
    <tr>
        <td><div class="label">Payment date</div>{{ $run->payment_date->toDateString() }}</td>
        <td><div class="label">Bank</div>{{ $payslip->bank_name ?? '—' }} @if ($payslip->bank_account_last4) ••••{{ $payslip->bank_account_last4 }} @endif</td>
        <td><div class="label">Working / paid days</div>{{ $payslip->working_days }} / {{ $payslip->paid_days }}</td>
        <td><div class="label">Currency</div>{{ $payslip->currency }}</td>
    </tr>
</table>

<table class="columns">
    <tr>
        <td>
            <table class="lines">
                <thead><tr><th>Earnings</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach ($earnings as $line)
                    <tr><td>{{ $line->name }}</td><td class="num">{{ $money($line->amount) }}</td></tr>
                @endforeach
                <tr class="total"><td>Gross</td><td class="num">{{ $money($payslip->gross) }}</td></tr>
                </tbody>
            </table>
        </td>
        <td>
            <table class="lines">
                <thead><tr><th>Deductions</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($deductions as $line)
                    <tr><td>{{ $line->name }}</td><td class="num">{{ $money($line->amount) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">No deductions</td></tr>
                @endforelse
                <tr class="total"><td>Total deductions</td><td class="num">{{ $money($payslip->total_deductions) }}</td></tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

<table class="net">
    <tr><td>Net pay</td><td class="num">{{ $payslip->currency }} {{ $money($payslip->net) }}</td></tr>
</table>

@if ($employer->isNotEmpty())
    <table class="lines" style="margin-top: 16px; width: 50%;">
        <thead><tr><th>Employer contributions (not deducted)</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($employer as $line)
            <tr><td>{{ $line->name }}</td><td class="num">{{ $money($line->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Taxable income {{ $money($payslip->taxable) }}. Withholding is calculated from the company's configured tax table.
    This payslip is confidential. Generated {{ now()->toDateString() }}.
</div>
</body>
</html>
