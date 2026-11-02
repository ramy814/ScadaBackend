<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        @page {
            margin: 10mm;
        }

        body {
            direction: rtl;
            margin: 0;
            background: #ffffff;
            color: #172033;
            font-family: dejavusans, sans-serif;
            font-size: 11px;
            line-height: 1.5;
        }

        body.format-excel {
            font-size: 12pt;
        }

        .report-header {
            margin-bottom: 14px;
            padding: 14px 16px;
            border: 1px solid #93c5fd;
            background: #eff6ff;
        }

        h1 {
            margin: 0 0 7px;
            text-align: center;
            color: #164e63;
            font-size: 26px;
        }

        .meta {
            text-align: center;
            color: #475569;
            font-size: 12px;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .summary-card {
            width: 20%;
            padding: 10px;
            border: 1px solid #bfdbfe;
            background: #f8fafc;
            text-align: center;
        }

        .summary-label {
            display: block;
            color: #475569;
            font-size: 10px;
        }

        .summary-value {
            display: block;
            margin-top: 3px;
            color: #164e63;
            font-size: 17px;
            font-weight: bold;
        }

        .summary-card.is-alert {
            border-color: #fca5a5;
            background: #fff1f2;
        }

        .summary-card.is-alert .summary-value {
            color: #b91c1c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            border: 1px solid #94a3b8;
            padding: 7px 6px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background: #164e63;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .total-row td {
            background: #dbeafe;
            color: #0f172a;
            font-weight: bold;
        }

        .text-cell {
            text-align: right;
        }

        .date-cell,
        .number-cell,
        .coordinates-cell {
            direction: ltr;
            white-space: nowrap;
        }

        .missing-row td {
            background: #fff1f2 !important;
        }

        .status-badge {
            display: inline-block;
            min-width: 80px;
            padding: 4px 7px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-normal { border-color: #86efac; background: #ecfdf5; color: #166534; }
        .status-first { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
        .status-review { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
        .status-abnormal { border-color: #fca5a5; background: #fff1f2; color: #b91c1c; }
        .status-missing { border-color: #fca5a5; background: #fff1f2; color: #b91c1c; }

        .empty-row td {
            padding: 24px;
            color: #64748b;
            font-size: 14px;
        }
    </style>
</head>
@php
    $filters = $report['filters'] ?? [];
    $rows = collect($report['rows'] ?? []);
    $columns = collect($report['columns'] ?? []);
    $summary = $report['summary'] ?? [];
    $totals = $report['totals'] ?? [];
    $dateFrom = $filters['date_from'] ?? null;
    $dateTo = $filters['date_to'] ?? null;
    $period = $dateFrom || $dateTo
        ? trim(($dateFrom ?: 'البداية') . ' - ' . ($dateTo ?: 'النهاية'))
        : 'كل الفترات';
    $number = fn ($value) => $value === null || $value === ''
        ? '-'
        : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $date = function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $statusClass = fn (string $key): string => match ($key) {
        'normal' => 'status-normal',
        'first' => 'status-first',
        'review' => 'status-review',
        'abnormal' => 'status-abnormal',
        default => 'status-missing',
    };
@endphp
<body class="format-{{ $format ?? 'pdf' }}">
<div class="report-header">
    <h1>تقرير إنتاج الآبار</h1>
    <div class="meta">
        الفترة: {{ $period }}
        &nbsp; | &nbsp;
        تاريخ الإنشاء: {{ $date($report['generated_at'] ?? now()) }}
    </div>
</div>

<table class="summary-table">
    <tr>
        <td class="summary-card">
            <span class="summary-label">الآبار الفعّالة</span>
            <span class="summary-value">{{ $number($summary['total_active_wells'] ?? 0) }}</span>
        </td>
        <td class="summary-card">
            <span class="summary-label">آبار ذات قراءة</span>
            <span class="summary-value">{{ $number($summary['reported_wells'] ?? 0) }}</span>
        </td>
        <td class="summary-card {{ ($summary['missing_wells'] ?? 0) > 0 ? 'is-alert' : '' }}">
            <span class="summary-label">آبار غير مدخلة</span>
            <span class="summary-value">{{ $number($summary['missing_wells'] ?? 0) }}</span>
        </td>
        <td class="summary-card">
            <span class="summary-label">نسبة الاكتمال</span>
            <span class="summary-value">{{ $number($summary['completion_percentage'] ?? 0) }}%</span>
        </td>
        <td class="summary-card {{ ($summary['review_wells'] ?? 0) > 0 ? 'is-alert' : '' }}">
            <span class="summary-label">تحتاج مراجعة</span>
            <span class="summary-value">{{ $number($summary['review_wells'] ?? 0) }}</span>
        </td>
    </tr>
</table>

<table class="report-table">
    <thead>
        <tr>
            <th>#</th>
            @foreach ($columns as $column)
                <th>{{ $column['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr class="{{ empty($row['has_reading']) ? 'missing-row' : '' }}">
                <td>{{ $row['index'] }}</td>
                @foreach ($columns as $column)
                    @php
                        $key = $column['key'];
                        $type = $column['type'] ?? 'text';
                        $value = $row[$key] ?? null;
                    @endphp
                    <td class="{{ $type === 'number' ? 'number-cell' : ($type === 'date' ? 'date-cell' : ($key === 'coordinates' ? 'coordinates-cell' : 'text-cell')) }}">
                        @if ($type === 'status')
                            @php $status = is_array($value) ? $value : ['key' => 'missing', 'label' => '-']; @endphp
                            <span class="status-badge {{ $statusClass($status['key'] ?? 'missing') }}">
                                {{ $status['label'] ?? '-' }}
                            </span>
                        @elseif ($type === 'date')
                            {{ $date($value) }}
                        @elseif ($type === 'number')
                            {{ $number($value) }}
                        @else
                            {{ $value === null || $value === '' ? '-' : $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr class="empty-row">
                <td colspan="{{ $columns->count() + 1 }}">لا توجد آبار فعّالة مطابقة</td>
            </tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total-row">
                <td>الإجمالي</td>
                @foreach ($columns as $column)
                    <td class="{{ ($column['type'] ?? null) === 'number' ? 'number-cell' : '' }}">
                        {{ array_key_exists($column['key'], $totals) ? $number($totals[$column['key']]) : '' }}
                    </td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
</body>
</html>
