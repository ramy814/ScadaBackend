<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            direction: rtl;
            color: #111827;
            font-size: 9px;
        }
        h1 {
            margin: 0 0 8px;
            text-align: center;
            font-size: 20px;
        }
        .meta {
            margin-bottom: 12px;
            text-align: center;
            color: #374151;
        }
        .summary-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: separate;
            border-spacing: 4px 0;
        }
        .summary-card {
            width: 20%;
            padding: 6px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            text-align: center;
        }
        .summary-label {
            display: block;
            color: #475569;
            font-size: 8px;
        }
        .summary-value {
            display: block;
            margin-top: 2px;
            color: #164e63;
            font-size: 13px;
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
        .report-table {
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #94a3b8;
            padding: 4px 3px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background: #dbeafe;
            font-weight: bold;
        }
        .title-row th {
            background: #bfdbfe;
            font-size: 14px;
        }
        .total-row td {
            background: #e5e7eb;
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
            background: #fff1f2;
        }
        .status-badge {
            display: inline-block;
            min-width: 58px;
            padding: 2px 5px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            font-weight: bold;
            white-space: nowrap;
        }
        .status-normal { border-color: #86efac; background: #ecfdf5; color: #166534; }
        .status-first { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
        .status-review { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
        .status-missing { border-color: #fca5a5; background: #fff1f2; color: #b91c1c; }
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $rows = $report['rows'] ?? collect();
    $summary = $report['summary'] ?? [];
    $dateFrom = $filters['date_from'] ?? null;
    $dateTo = $filters['date_to'] ?? null;
    $period = $dateFrom || $dateTo
        ? trim(($dateFrom ?: 'البداية') . ' - ' . ($dateTo ?: 'النهاية'))
        : 'كل الفترات';
    $number = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
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
@endphp

<h1>تقرير إنتاج الآبار</h1>
<div class="meta">
    الفترة: {{ $period }} |
    تاريخ الإنشاء: {{ $date($report['generated_at'] ?? now()) }}
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
            <th width="2%">#</th>
            <th width="7%">المؤشر</th>
            <th width="10%">اسم البئر</th>
            <th width="8%">رقم/اسم رسمي</th>
            <th width="10%">الإحداثيات</th>
            <th width="6%">السعة (م³/ساعة)</th>
            <th width="6%">القراءة السابقة</th>
            <th width="6%">القراءة الحالية</th>
            <th width="7%">فرق التشغيل (ساعة)</th>
            <th width="7%">الإنتاج (م³)</th>
            <th width="10%">المفتش</th>
            <th width="9%">تاريخ القراءة</th>
            <th width="12%">ملاحظات</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php
                $status = $row['status'] ?? ['key' => 'missing', 'label' => 'غير مدخلة'];
            @endphp
            <tr class="{{ empty($row['has_reading']) ? 'missing-row' : '' }}">
                <td>{{ $row['index'] }}</td>
                <td>
                    <span class="status-badge status-{{ $status['key'] }}">
                        {{ $status['label'] }}
                    </span>
                </td>
                <td class="text-cell">{{ $row['well_name'] }}</td>
                <td>{{ $row['well_number'] }}</td>
                <td class="coordinates-cell">{{ $row['coordinates'] ?: '-' }}</td>
                <td class="number-cell">{{ $number($row['capacity']) }}</td>
                <td class="number-cell">{{ $number($row['previous_reading']) }}</td>
                <td class="number-cell">{{ $number($row['current_reading']) }}</td>
                <td class="number-cell">{{ $number($row['operating_hours']) }}</td>
                <td class="number-cell">{{ $number($row['production']) }}</td>
                <td class="text-cell">{{ $row['inspector_name'] }}</td>
                <td class="date-cell">{{ $date($row['reading_date']) }}</td>
                <td class="text-cell">{{ $row['notes'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="13">لا توجد آبار فعّالة مطابقة</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td>المجموع</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="number-cell">{{ $number($report['total_operating_hours'] ?? 0) }}</td>
            <td class="number-cell">{{ $number($report['total_production'] ?? 0) }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>
