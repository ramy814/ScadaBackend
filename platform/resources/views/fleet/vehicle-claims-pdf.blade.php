<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a2740; }
        .org { font-size: 11px; color: #5f6f8b; }
        .title { font-size: 17px; font-weight: bold; margin: 10px 0 4px; text-align: center; }
        .sub { color: #5f6f8b; margin-bottom: 10px; text-align: center; }
        table.meta { margin-top: 4px; }
        table.meta td { border: none; padding: 3px 4px; }
        table.meta td.value { padding-left: 16px; }
        .label { color: #5f6f8b; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #c9d5e8; padding: 5px 6px; text-align: right; vertical-align: top; }
        th { background: #eef3fa; }
        .num { text-align: center; }
        .total td { font-weight: bold; background: #f6f8fc; }
        .note { margin-top: 8px; color: #92400e; font-size: 10px; }
        .signatures { width: 100%; margin-top: 40px; border: none; }
        .signatures td { border: none; text-align: center; padding-top: 30px; }
        .vehicle-title { font-size: 14px; font-weight: bold; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="org">دائرة الشؤون الإدارية — قسم حركة الآليات</div>
    <div class="title">مطالبة مالية لعمل المركبات</div>
    <div class="sub">الرحلات المعتمدة فقط — المبلغ = ساعات العمل × سعر الساعة المثبّت عند الاعتماد</div>
    <table class="meta">
        <tr>
            <td class="label">من تاريخ</td><td class="value">{{ $filters['date_from'] }}</td>
            <td class="label">إلى تاريخ</td><td class="value">{{ $filters['date_to'] }}</td>
            <td class="label">عدد المركبات</td><td class="value">{{ $totals['vehicles'] }}</td>
            <td class="label">تاريخ الإصدار</td><td class="value">{{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th class="num">#</th>
                <th>رقم المركبة</th>
                <th>اسم المركبة</th>
                <th>السائق</th>
                <th class="num">عدد الرحلات</th>
                <th class="num">ساعات العمل</th>
                <th class="num">سعر الساعة</th>
                <th class="num">المبلغ (شيكل)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ $row['plate_number'] ?? '-' }}</td>
                    <td>{{ $row['vehicle_name'] ?? '-' }}</td>
                    <td>{{ $row['driver_name'] ?? '-' }}</td>
                    <td class="num">{{ $row['trips_count'] }}</td>
                    <td class="num">{{ $row['work_time'] }}</td>
                    <td class="num">{{ $row['price_label'] }}</td>
                    <td class="num">{{ number_format($row['amount'], 2) }}@if($row['unpriced_trips'] || $row['current_price_trips']) *@endif</td>
                </tr>
            @empty
                <tr><td colspan="8">لا توجد رحلات معتمدة ضمن الفترة المحددة.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="4">الإجمالي</td>
                <td class="num">{{ $totals['trips'] }}</td>
                <td class="num">{{ $totals['work_time'] }}</td>
                <td></td>
                <td class="num">{{ number_format($totals['amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($totals['current_price_trips'] || $totals['unpriced_trips'])
        <div class="note">
            * @if($totals['current_price_trips']){{ $totals['current_price_trips'] }} رحلة اعتُمدت قبل تثبيت السعر فحُسبت بسعر الساعة الحالي. @endif
            @if($totals['unpriced_trips']){{ $totals['unpriced_trips'] }} رحلة لمركبات بلا سعر ساعة لم تدخل في المبلغ. @endif
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>رئيس شعبة السيارات المستأجرة<br><br>..............................</td>
            <td>رئيس قسم حركة الآليات<br><br>..............................</td>
            <td>مدير الدائرة<br><br>..............................</td>
        </tr>
    </table>

    @foreach($rows as $row)
        <pagebreak />
        <div class="org">دائرة الشؤون الإدارية — قسم حركة الآليات</div>
        <table class="meta">
            <tr>
                <td class="vehicle-title">بطاقة عمل المركبة</td>
                <td class="vehicle-title">{{ $row['plate_number'] ?? '-' }}</td>
                <td class="vehicle-title">{{ $row['vehicle_name'] ?? '' }}</td>
            </tr>
        </table>
        <table class="meta">
            <tr>
                <td class="label">السائق</td><td class="value">{{ $row['driver_name'] ?? '-' }}</td>
                <td class="label">من تاريخ</td><td class="value">{{ $filters['date_from'] }}</td>
                <td class="label">إلى تاريخ</td><td class="value">{{ $filters['date_to'] }}</td>
                <td class="label">سعر الساعة</td><td class="value">{{ $row['price_label'] }}</td>
            </tr>
        </table>
        <table>
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th class="num">عدد الرحلات</th>
                    <th class="num">ساعات العمل</th>
                    <th class="num">المبلغ (شيكل)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($row['days'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td class="num">{{ $day['trips_count'] }}</td>
                        <td class="num">{{ $day['work_time'] }}</td>
                        <td class="num">{{ number_format($day['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>الإجمالي</td>
                    <td class="num">{{ $row['trips_count'] }}</td>
                    <td class="num">{{ $row['work_time'] }}</td>
                    <td class="num">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>
