<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة أنواع المهام</title>
    <style>
        body { margin:0; font-family:Tahoma,Arial,sans-serif; background:#f3f6fb; color:#1a2740; }
        .top { padding:14px 20px; background:#fff; border-bottom:1px solid #d9e3f2; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .nav { display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
        .nav a { text-decoration:none; border:1px solid #d9e3f2; padding:6px 10px; border-radius:8px; color:#1a2740; background:#fff; font-size:12px; }
        .nav a.active { background:#1f6feb; border-color:#1f6feb; color:#fff; }
        .wrap { padding:16px; display:grid; gap:12px; }
        .card { background:#fff; border:1px solid #d9e3f2; border-radius:10px; padding:12px; }
        .row { display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; }
        input, select { border:1px solid #d6e0f0; border-radius:8px; padding:8px 10px; font-size:13px; }
        button { border:none; border-radius:8px; padding:8px 12px; background:#1f6feb; color:#fff; cursor:pointer; }
        table { width:100%; border-collapse:collapse; }
        th, td { border-bottom:1px solid #edf2fb; padding:8px; font-size:13px; text-align:right; }
    </style>
</head>
<body>
<div class="top">
    <div>
        <div style="font-size:20px;font-weight:700;">إدارة أنواع المهام</div>
        <div class="nav">
            <a href="{{ route('admin.dashboard') }}">الداشبورد</a>

            <a href="{{ route('admin.locations') }}">المواقع</a>
            <a class="active" href="{{ route('admin.mission-types') }}">أنواع المهام</a>
            <a href="{{ route('admin.reports') }}">التقارير</a>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.logout') }}">@csrf <button type="submit" style="background:#dc2626">تسجيل خروج</button></form>
</div>
<div class="wrap">
    @if (session('ok'))
        <div class="card" style="border-color:#b6e9c7;color:#156a35;background:#e7f8ee;">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="card" style="border-color:#f5c2c7;color:#842029;background:#f8d7da;">{{ session('error') }}</div>
    @endif
    @if (!empty($tableMissing))
        <div class="card" style="border-color:#f5c2c7;color:#842029;background:#f8d7da;">
            جدول <b>MISSION_TYPES</b> غير موجود في Oracle. يرجى تنفيذ الترحيلات ثم إعادة المحاولة.
        </div>
    @endif
    <div class="card">
        <form method="POST" action="{{ route('admin.mission-types.store') }}" class="row">
            @csrf
            <input name="name" placeholder="اسم نوع المهمة" required>
            <input type="number" name="sort_order" placeholder="الترتيب" value="0" min="0">
            <select name="active"><option value="1">مفعل</option><option value="0">غير مفعل</option></select>
            <button type="submit">إضافة</button>
        </form>
    </div>
    <div class="card">
        <table>
            <thead><tr><th>الاسم</th><th>الترتيب</th><th>الحالة</th><th>حفظ</th></tr></thead>
            <tbody>
            @if($items->isEmpty())
                <tr><td colspan="4" style="text-align:center;color:#64748b;">لا توجد أنواع مهام حالياً.</td></tr>
            @endif
            @foreach($items as $item)
                <tr>
                    <form method="POST" action="{{ route('admin.mission-types.update', ['missionType' => $item->id]) }}">
                        @csrf
                        <td><input name="name" value="{{ $item->name }}" required></td>
                        <td><input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0"></td>
                        <td>
                            <select name="active">
                                <option value="1" @selected($item->active)>مفعل</option>
                                <option value="0" @selected(!$item->active)>غير مفعل</option>
                            </select>
                        </td>
                        <td><button type="submit">حفظ</button></td>
                    </form>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
