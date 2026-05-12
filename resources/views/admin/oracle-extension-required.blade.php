<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>متطلب Oracle</title>
    <style>
        body { margin: 0; font-family: Tahoma, Arial, sans-serif; background: #f3f6fb; color: #1a2740; }
        .wrap { max-width: 760px; margin: 40px auto; padding: 16px; }
        .card { background: #fff; border: 1px solid #d9e3f2; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 10px; font-size: 20px; }
        p { margin: 0 0 10px; line-height: 1.7; }
        code { background: #f6f8fc; border: 1px solid #e3e9f5; border-radius: 6px; padding: 2px 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>لا يمكن فتح الداشبورد الآن</h1>
        <p>النظام مضبوط على قاعدة بيانات Oracle لكن امتداد <code>oci8</code> غير مفعّل في PHP الحالي.</p>
        <p>الإجراء المطلوب: تفعيل Oracle Instant Client + امتداد <code>oci8</code> لنفس نسخة PHP المستخدمة لتشغيل Laravel.</p>
        <p>بعد التفعيل شغّل:</p>
        <p><code>php -m | rg oci</code></p>
        <p>إذا ظهر <code>oci8</code> أعد تشغيل السيرفر وستعمل البيانات من Oracle مباشرة.</p>
    </div>
</div>
</body>
</html>
