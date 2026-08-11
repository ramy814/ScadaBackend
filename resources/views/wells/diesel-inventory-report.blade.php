<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        @page {
            margin: 8mm;
        }

        body {
            direction: rtl;
            margin: 0;
            background: #ffffff;
            color: #172033;
            font-family: dejavusans, sans-serif;
            font-size: 8.5px;
        }

        .report-header {
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
        }

        .report-title {
            margin: 0 0 5px;
            text-align: center;
            color: #164e63;
            font-size: 18px;
        }

        .meta {
            text-align: center;
            color: #475569;
            font-size: 9px;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: separate;
            border-spacing: 4px 0;
        }

        .summary-card {
            width: 25%;
            padding: 6px 8px;
            border: 1px solid #dbeafe;
            background: #f8fafc;
            text-align: center;
        }

        .summary-label {
            display: block;
            margin-bottom: 2px;
            color: #64748b;
            font-size: 7.5px;
        }

        .summary-value {
            color: #0f3f56;
            font-size: 12px;
            font-weight: bold;
        }

        .summary-card.is-alert {
            border-color: #fecaca;
            background: #fff1f2;
        }

        .summary-card.is-alert .summary-value {
            color: #b91c1c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 4px 3px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background: #164e63;
            color: #ffffff;
            font-weight: bold;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .total-row td {
            border-color: #94a3b8;
            background: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
        }

        .text-cell {
            text-align: right;
        }

        .notes-cell {
            text-align: right;
            color: #475569;
            font-size: 7.5px;
        }

        .number-cell,
        .date-cell,
        .ltr-cell {
            direction: ltr;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            min-width: 72px;
            padding: 3px 6px;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            color: #475569;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-normal {
            border-color: #86efac;
            background: #ecfdf5;
            color: #166534;
        }

        .status-high {
            border-color: #fca5a5;
            background: #fff1f2;
            color: #b91c1c;
        }

        .status-invalid {
            border-color: #fcd34d;
            background: #fffbeb;
            color: #92400e;
        }

        .status-unavailable {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #64748b;
        }

        .status-reason {
            margin-top: 2px;
            color: #64748b;
            font-size: 6.5px;
            line-height: 1.35;
        }

        .empty-row td {
            padding: 18px;
            background: #ffffff;
            color: #64748b;
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $rows = collect($report['rows'] ?? []);
    $columns = collect($report['columns'] ?? []);
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
        'high' => 'status-high',
        'invalid' => 'status-invalid',
        default => 'status-unavailable',
    };
    $unavailableReason = function (array $row, string $key, mixed $value): ?string {
        if ($value !== null && $value !== '') {
            return null;
        }

        if (in_array($key, [
            'previous_inventory_date',
            'previous_generator_reading',
            'previous_diesel_height_cm',
        ], true)) {
            return $row['previous_inventory_unavailable_reason'] ?? null;
        }

        return $key === 'supplied_fuel_liters'
            ? ($row['supplied_fuel_unavailable_reason'] ?? null)
            : null;
    };
    $generatedAt = $report['generated_at'] ?? now();
    $totalOperatingHours = $totals['operating_hours'] ?? $report['total_operating_hours'] ?? null;
    $totalSuppliedFuel = $totals['supplied_fuel_liters'] ?? $report['total_supplied_fuel_liters'] ?? null;
    $highConsumptionCount = (int) ($report['high_consumption_count'] ?? 0);
@endphp

<div class="report-header">
    <h1 class="report-title">{{ $report['title'] ?? 'تقرير جرد السولار للآبار' }}</h1>
    <div class="meta">
        الفترة: {{ $period }}
        &nbsp; | &nbsp;
        تاريخ الإنشاء: {{ $date($generatedAt) }}
    </div>
</div>

<table class="summary-table">
    <tr>
        <td class="summary-card">
            <span class="summary-label">عدد السجلات</span>
            <span class="summary-value">{{ $number($rows->count()) }}</span>
        </td>
        <td class="summary-card">
            <span class="summary-label">إجمالي ساعات التشغيل</span>
            <span class="summary-value">{{ $number($totalOperatingHours) }}</span>
        </td>
        <td class="summary-card">
            <span class="summary-label">إجمالي السولار المورد/المعبأ (لتر)</span>
            <span class="summary-value">{{ $number($totalSuppliedFuel) }}</span>
        </td>
        <td class="summary-card {{ $highConsumptionCount > 0 ? 'is-alert' : '' }}">
            <span class="summary-label">حالات الاستهلاك المرتفع</span>
            <span class="summary-value">{{ $number($highConsumptionCount) }}</span>
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
            <tr>
                <td class="number-cell">{{ $row['index'] ?? $loop->iteration }}</td>
                @foreach ($columns as $column)
                    @php
                        $key = $column['key'];
                        $type = $column['type'] ?? 'text';
                        $value = data_get($row, $key);
                        $missingReason = $unavailableReason($row, $key, $value);
                    @endphp
                    @if ($missingReason)
                        <td class="notes-cell">{{ $missingReason }}</td>
                    @elseif ($type === 'number')
                        <td class="number-cell">{{ $number($value) }}</td>
                    @elseif ($type === 'date')
                        <td class="date-cell">{{ $date($value) }}</td>
                    @elseif ($type === 'status')
                        @php
                            $status = is_array($value) ? $value : [];
                            $statusKey = (string) ($status['key'] ?? 'unavailable');
                        @endphp
                        <td>
                            <span class="status-badge {{ $statusClass($statusKey) }}">
                                {{ $status['label'] ?? 'بيانات غير مكتملة' }}
                            </span>
                            @if (! empty($status['reason']))
                                <div class="status-reason">{{ $status['reason'] }}</div>
                            @endif
                        </td>
                    @else
                        <td class="{{ $key === 'notes' ? 'notes-cell' : 'text-cell' }} {{ $key === 'generator_number' ? 'ltr-cell' : '' }}">
                            {{ $value === null || $value === '' ? '-' : $value }}
                        </td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr class="empty-row">
                <td colspan="{{ $columns->count() + 1 }}">لا توجد سجلات ضمن الفترة والفلاتر المحددة</td>
            </tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total-row">
                <td>المجموع</td>
                @foreach ($columns as $column)
                    @php
                        $key = $column['key'];
                        $hasTotal = array_key_exists($key, $totals);
                    @endphp
                    <td class="{{ ($column['type'] ?? null) === 'number' ? 'number-cell' : '' }}">
                        {{ $hasTotal ? $number($totals[$key]) : '' }}
                    </td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
</body>
</html>
