<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->name() }}</title>
    <style>
        @page { margin: 28px 30px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2933; }
        .muted { color: #6b7785; }
        .header { width: 100%; border-bottom: 2px solid #1f4e79; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 14px; font-weight: bold; color: #1f4e79; }
        .report-name { font-size: 16px; font-weight: bold; text-align: right; color: #1f4e79; }
        .params { margin-bottom: 10px; }
        .params span { display: inline-block; margin-right: 14px; }
        .label { font-size: 8px; text-transform: uppercase; color: #6b7785; letter-spacing: .4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1f4e79; color: #fff; font-weight: bold; padding: 5px 4px; text-align: left; font-size: 8.5px; }
        table.data td { padding: 4px; border-bottom: 1px solid #e3e8ee; }
        table.data tr.totals td { border-top: 2px solid #1f4e79; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .empty { padding: 16px; text-align: center; color: #6b7785; }
        .note { margin-top: 8px; color: #9a3412; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #6b7785; }
    </style>
</head>
<body>
@php
    $format = function ($value, string $type) {
        if ($value === null || $value === '') {
            return '';
        }
        if ($type === 'money' && is_numeric($value)) {
            return number_format((float) $value, 2, '.', ',');
        }

        return (string) $value;
    };
@endphp
<table class="header">
    <tr>
        <td>
            <span class="company-name">{{ $company->legal_name ?: $company->name }}</span><br>
            <span class="muted">Currency: {{ $company->currency }}</span>
        </td>
        <td>
            <div class="report-name">{{ $report->name() }}</div>
            <div class="muted" style="text-align: right;">{{ $report->description() }}</div>
        </td>
    </tr>
</table>

<div class="params">
    @foreach ($params as $label => $value)
        <span><span class="label">{{ $label }}</span> {{ $value }}</span>
    @endforeach
    <span><span class="label">Generated</span> {{ $generatedAt }} by {{ $generatedBy }}</span>
</div>

<table class="data">
    <thead>
    <tr>
        @foreach ($columns as $column)
            <th class="{{ in_array($column['type'], ['money', 'number'], true) ? 'num' : '' }}">{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            @foreach ($columns as $column)
                <td class="{{ in_array($column['type'], ['money', 'number'], true) ? 'num' : '' }}">{{ $format($row[$column['key']] ?? null, $column['type']) }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td class="empty" colspan="{{ count($columns) }}">No data for the selected parameters.</td></tr>
    @endforelse
    @if ($totals)
        <tr class="totals">
            @foreach ($columns as $column)
                <td class="{{ in_array($column['type'], ['money', 'number'], true) ? 'num' : '' }}">{{ $format($totals[$column['key']] ?? null, $column['type']) }}</td>
            @endforeach
        </tr>
    @endif
    </tbody>
</table>

@if ($truncated)
    <p class="note">Only the first {{ number_format($maxRows) }} rows are shown in PDF. Export as CSV or XLSX for the full data set.</p>
@endif

<div class="footer">{{ $company->name }} · {{ $report->name() }} · {{ $generatedAt }}</div>
</body>
</html>
