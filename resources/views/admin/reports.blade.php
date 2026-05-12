<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير</title>
    <style>
        :root { --bg:#f3f6fb; --surface:#fff; --stroke:#d9e3f2; --text:#1a2740; --muted:#5f6f8b; --brand:#1f6feb; --ok:#15803d; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Tahoma, Arial, sans-serif; background:var(--bg); color:var(--text); }
        .top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:14px 20px; background:var(--surface); border-bottom:1px solid var(--stroke); }
        .title { font-size:20px; font-weight:700; } .sub { color:var(--muted); font-size:13px; margin-top:4px; }
        .nav { display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
        .nav a { text-decoration:none; border:1px solid var(--stroke); background:#fff; color:var(--text); padding:6px 10px; border-radius:8px; font-size:12px; }
        .nav a.active { background:var(--brand); color:#fff; border-color:var(--brand); }
        .logout { border:none; border-radius:8px; padding:9px 12px; background:#dc2626; color:#fff; cursor:pointer; }
        .wrap { padding:16px; display:grid; gap:12px; }
        .card { background:#fff; border:1px solid var(--stroke); border-radius:10px; padding:12px; }
        .bar { display:flex; gap:8px; flex-wrap:wrap; }
        .seg a, .select, .btn, .input { border:1px solid #d6e0f0; border-radius:8px; padding:8px 10px; font-size:13px; background:#fff; text-decoration:none; color:var(--text); }
        .seg a.active { background:var(--brand); border-color:var(--brand); color:#fff; }
        .btn { border:none; background:var(--brand); color:#fff; cursor:pointer; }
        .btn.csv { background:var(--ok); }
        .btn.pdf { background:#b45309; }
        .stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .label { font-size:12px; color:var(--muted);} .value { font-size:24px; font-weight:700; margin-top:6px;}
        .panel { background:#fff; border:1px solid var(--stroke); border-radius:10px; overflow:hidden; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:1100px; }
        th, td { text-align:right; padding:10px; border-bottom:1px solid #edf2fb; font-size:13px; }
        th { background:#fbfdff; color:#4e6285; }
    </style>
</head>
<body>
<div class="top">
    <div>
        <div class="title">التقارير</div>
        <div class="sub">تقارير يومية وأسبوعية وشهرية مع تصدير CSV</div>
        <div class="nav">
            <a href="{{ route('admin.dashboard') }}">الداشبورد</a>

            <a href="{{ route('admin.locations') }}">المواقع</a>
            <a href="{{ route('admin.mission-types') }}">أنواع المهام</a>
            <a class="active" href="{{ route('admin.reports') }}">التقارير</a>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.logout') }}">@csrf <button class="logout" type="submit">تسجيل خروج</button></form>
</div>
<div class="wrap">
    <section class="card">
        <form method="GET" class="bar">
            <div class="seg">
                <a class="{{ $period === 'daily' ? 'active' : '' }}" href="{{ route('admin.reports', ['period' => 'daily', 'driver_id' => $driverId, 'vehicle_id' => $vehicleId]) }}">يومي</a>
                <a class="{{ $period === 'weekly' ? 'active' : '' }}" href="{{ route('admin.reports', ['period' => 'weekly', 'driver_id' => $driverId, 'vehicle_id' => $vehicleId]) }}">أسبوعي</a>
                <a class="{{ $period === 'monthly' ? 'active' : '' }}" href="{{ route('admin.reports', ['period' => 'monthly', 'driver_id' => $driverId, 'vehicle_id' => $vehicleId]) }}">شهري</a>
            </div>
            <select class="select" name="driver_id">
                <option value="">كل السائقين</option>
                @foreach($drivers as $driver)
                    <option value="{{ $driver->id }}" @selected((string)$driverId === (string)$driver->id)>{{ $driver->name }}</option>
                @endforeach
            </select>
            <select class="select" name="vehicle_id">
                <option value="">كل المركبات</option>
                @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" @selected((string)$vehicleId === (string)$vehicle->id)>
                        {{ $vehicle->plate_number }} - {{ $vehicle->name }}
                    </option>
                @endforeach
            </select>
            <button class="btn" type="submit">تحديث</button>
            <a class="btn csv" href="{{ route('admin.reports.csv', ['period' => $period, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId]) }}">تنزيل CSV</a>
            <a class="btn pdf" href="{{ route('admin.reports.pdf', ['period' => $period, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId]) }}">تنزيل PDF</a>
        </form>
    </section>

    <section class="stats">
        <article class="card"><div class="label">عدد الرحلات</div><div class="value">{{ $summary['trips_count'] }}</div></article>
        <article class="card"><div class="label">إجمالي الوقود الافتراضي (لتر)</div><div class="value">{{ $summary['sum_virtual_fuel'] }}</div></article>
    </section>

    <section class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th><th>الوقت</th><th>السائق</th><th>المركبة</th><th>الانطلاق</th><th>الوجهة</th>
                    <th>الحالة</th><th>المراجعة</th><th>وقود افتراضي</th><th>النقاط</th>
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
                    <tr><td colspan="10">لا توجد بيانات ضمن الفلاتر المحددة.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:10px;">{{ $trips->links() }}</div>
    </section>
</div>
</body>
</html>
