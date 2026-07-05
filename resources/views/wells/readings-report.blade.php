<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            direction: rtl;
            color: #111827;
            font-size: 12px;
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #111827;
            padding: 6px;
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
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $rows = $report['rows'] ?? collect();
    $dateFrom = $filters['date_from'] ?? null;
    $dateTo = $filters['date_to'] ?? null;
    $period = $dateFrom || $dateTo
        ? trim(($dateFrom ?: 'البداية') . ' - ' . ($dateTo ?: 'النهاية'))
        : 'كل الفترات';
    $number = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

<h1>تقرير إنتاج الآبار</h1>
<div class="meta">
    الفترة: {{ $period }} |
    تاريخ الإنشاء: {{ optional($report['generated_at'] ?? now())->format('Y-m-d H:i') }}
</div>

<table>
    <thead>
        <tr class="title-row">
            <th colspan="5">بيانات البئر</th>
            <th colspan="4">قراءات العداد والإنتاج</th>
            <th>ملاحظات</th>
        </tr>
        <tr>
            <th>#</th>
            <th>اسم البئر</th>
            <th>رقم/اسم رسمي</th>
            <th>الإحداثيات</th>
            <th>السعة (م³/ساعة)</th>
            <th>القراءة السابقة</th>
            <th>القراءة الحالية</th>
            <th>فرق التشغيل (ساعة)</th>
            <th>الإنتاج (م³)</th>
            <th>ملاحظات</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['index'] }}</td>
                <td class="text-cell">{{ $row['well_name'] }}</td>
                <td>{{ $row['well_number'] }}</td>
                <td>{{ $row['coordinates'] }}</td>
                <td>{{ $number($row['capacity']) }}</td>
                <td>{{ $number($row['previous_reading']) }}</td>
                <td>{{ $number($row['current_reading']) }}</td>
                <td>{{ $number($row['operating_hours']) }}</td>
                <td>{{ $number($row['production']) }}</td>
                <td class="text-cell">{{ $row['notes'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10">لا توجد قراءات ضمن الفترة المحددة</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="7">المجموع</td>
            <td>{{ $number($report['total_operating_hours'] ?? 0) }}</td>
            <td>{{ $number($report['total_production'] ?? 0) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>
