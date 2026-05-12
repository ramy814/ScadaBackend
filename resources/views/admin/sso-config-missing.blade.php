<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد الدخول الموحد</title>
    <style>
        body { margin:0; font-family:Tahoma, Arial, sans-serif; background:#f5f7fb; color:#1f2a44; }
        .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .card { width:100%; max-width:560px; background:#fff; border:1px solid #dbe3f0; border-radius:10px; padding:24px; }
        h1 { margin:0 0 12px; font-size:22px; }
        p { margin:0 0 14px; line-height:1.8; color:#4b5b7a; }
        code { display:block; direction:ltr; text-align:left; background:#f0f4fa; border:1px solid #dbe3f0; border-radius:8px; padding:10px 12px; margin:8px 0; }
        .warn { color:#b42318; font-weight:700; }
    </style>
</head>
<body>
    <div class="wrap">
        <section class="card">
            <h1>إعداد الدخول الموحد غير مكتمل</h1>
            <p>لا يمكن تحويل مدير النظام إلى بوابة البلدية لأن بيانات تطبيق SSO غير مضافة في ملف البيئة.</p>
            <p class="warn">القيم الناقصة:</p>
            @foreach($missing as $key)
                <code>{{ $key }}=</code>
            @endforeach
            <p>بعد إضافة القيم الفعلية شغّل <code>php artisan config:clear</code> ثم أعد فتح لوحة الإدارة.</p>
        </section>
    </div>
</body>
</html>
