<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a2740; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
        .sub { color: #5f6f8b; margin-bottom: 10px; }
        .meta { margin-bottom: 10px; }
        .meta span { margin-left: 14px; }
        .stats { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .stats td { border: 1px solid #d9e3f2; padding: 6px 8px; }
        .stats .label { color: #5f6f8b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d9e3f2; padding: 5px 6px; text-align: right; vertical-align: top; }
        th { background: #f3f6fb; }
    </style>
</head>
<body>
    <div class="title">تقرير حركة مركبات بلدية غزة</div>
    <div class="sub">تقرير {{ $period === 'daily' ? 'يومي' : ($period === 'weekly' ? 'أسبوعي' : 'شهري') }}</div>
    <div class="meta">
        <span>من: {{ $from->format('Y-m-d H:i') }}</span>
        <span>إلى: {{ $to->format('Y-m-d H:i') }}</span>
        <span>تاريخ الإصدار: {{ $generatedAt->format('Y-m-d H:i') }}</span>
    </div>

    <table class="stats">
        <tr>
            <td><div class="label">عدد الرحلات</div><div>{{ $summary['trips_count'] }}</div></td>
            <td><div class="label">إجمالي الوقود الافتراضي (لتر)</div><div>{{ $summary['sum_virtual_fuel'] }}</div></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>الوقت</th>
                <th>السائق</th>
                <th>المركبة</th>
                <th>الانطلاق</th>
                <th>الوجهة</th>
                <th>الحالة</th>
                <th>المراجعة</th>
                <th>وقود افتراضي</th>
                <th>النقاط</th>
            </tr>
        </thead>
        <tbody>
            @forelse($trips as $trip)
                <tr>
                    <td>{{ $trip->id }}</td>
                    <td>{{ optional($trip->started_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $trip->driver->name ?? '-' }}</td>
                    <td>{{ $trip->vehicle->plate_number ?? '-' }}</td>
                    <td>{{ $trip->origin_text }}</td>
                    <td>{{ $trip->destination_text }}</td>
                    <td>{{ $trip->status }}</td>
                    <td>{{ $trip->review_status }}</td>
                    <td>{{ $trip->virtual_fuel_liters ?? '-' }}</td>
                    <td>{{ $trip->points_received }} / {{ $trip->points_expected }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">لا توجد بيانات ضمن الفلاتر المحددة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
