<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body {
            direction: rtl;
            color: #111827;
            font-family: dejavusans, sans-serif;
            font-size: 8px;
        }
        h1 {
            margin: 0 0 4px;
            text-align: center;
            font-size: 17px;
        }
        .meta {
            margin-bottom: 8px;
            text-align: center;
            color: #374151;
            font-size: 9px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #111827;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background: #d1d5db;
            font-weight: bold;
        }
        .group-row th {
            background: #9ca3af;
            font-size: 9px;
        }
        .total-row td {
            background: #e5e7eb;
            font-weight: bold;
        }
        .text-cell {
            text-align: right;
        }
        .notes-cell {
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
    $number = fn ($value) => $value === null
        ? '-'
        : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

<h1>تقرير جرد السولار للآبار</h1>
<div class="meta">
    الفترة: {{ $period }} |
    تاريخ الإنشاء: {{ optional($report['generated_at'] ?? now())->format('Y-m-d H:i') }}
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>التاريخ</th>
            <th>اسم البئر</th>
            <th>الاسم/الرقم الرسمي</th>
            <th>المولد</th>
            <th>القدرة KVA</th>
            <th>الاستهلاك الاسمي لتر/ساعة</th>
            <th>قدرة ضخ البئر م³/ساعة</th>
            <th>القراءة السابقة</th>
            <th>القراءة الحالية</th>
            <th>ساعات التشغيل</th>
            <th>الارتفاع السابق سم</th>
            <th>الارتفاع الحالي سم</th>
            <th>التوريد لتر</th>
            <th>معدل الاستهلاك الفعلي لتر/ساعة</th>
            <th>سعة الخزان لتر</th>
            <th>إنتاج المياه م³</th>
            <th>استهلاك السولار المحسوب لتر</th>
            <th>ملاحظات</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['index'] }}</td>
                <td>{{ optional($row['inventory_date'])->format('Y-m-d H:i') }}</td>
                <td class="text-cell">{{ $row['well_name'] }}</td>
                <td>{{ $row['official_well_name'] }}</td>
                <td class="text-cell">{{ $row['generator_name'] }}<br>{{ $row['generator_number'] }}</td>
                <td>{{ $number($row['generator_capacity_kva']) }}</td>
                <td>{{ $number($row['generator_fuel_consumption_lph']) }}</td>
                <td>{{ $number($row['well_capacity_m3h']) }}</td>
                <td>{{ $number($row['previous_generator_reading']) }}</td>
                <td>{{ $number($row['current_generator_reading']) }}</td>
                <td>{{ $number($row['operating_hours']) }}</td>
                <td>{{ $number($row['previous_diesel_height_cm']) }}</td>
                <td>{{ $number($row['current_diesel_height_cm']) }}</td>
                <td>{{ $number($row['supplied_fuel_liters']) }}</td>
                <td>{{ $number($row['actual_consumption_lph']) }}</td>
                <td>{{ $number($row['tank_capacity_liters']) }}</td>
                <td>{{ $number($row['water_production_m3']) }}</td>
                <td>{{ $number($row['calculated_diesel_consumption_liters']) }}</td>
                <td class="notes-cell">{{ $row['notes'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="19">لا توجد سجلات جرد ضمن الفترة المحددة</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="10">المجموع</td>
            <td>{{ $number($report['total_operating_hours'] ?? 0) }}</td>
            <td colspan="2"></td>
            <td>{{ $number($report['total_supplied_fuel_liters'] ?? 0) }}</td>
            <td colspan="2"></td>
            <td>{{ $number($report['total_water_production_m3'] ?? 0) }}</td>
            <td>{{ $number($report['total_calculated_diesel_consumption_liters'] ?? 0) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>
