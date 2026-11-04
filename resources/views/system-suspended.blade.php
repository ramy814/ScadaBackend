<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>الأنظمة متوقفة مؤقتاً — بلدية غزة</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #09090b;
            color: #e2e8f0;
            font-family: Tahoma, "Segoe UI", sans-serif;
        }
        main {
            width: min(560px, 100%);
            padding: 40px;
            border: 1px solid #3f3f46;
            border-radius: 18px;
            background: #18181b;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .45);
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            padding: 8px 13px;
            border: 1px solid #7f1d1d;
            border-radius: 999px;
            background: #450a0a;
            color: #fecaca;
            font-weight: 700;
        }
        h1 { margin: 0 0 14px; color: #f8fafc; font-size: clamp(25px, 5vw, 34px); }
        p { margin: 0; color: #a1a1aa; line-height: 1.9; }
        .reason {
            margin-top: 22px;
            padding: 16px;
            border-radius: 12px;
            background: #27272a;
            color: #e4e4e7;
        }
        small { display: block; margin-top: 24px; color: #71717a; }
    </style>
</head>
<body>
    <main>
        <div class="status">إيقاف إداري مؤقت</div>
        <h1>أنظمة بلدية غزة متوقفة مؤقتاً</h1>
        <p>تم إيقاف البوابة وخدمات تطبيقات الميدان من لوحة إدارة الصلاحيات.</p>
        @if (filled($reason))
            <p class="reason">{{ $reason }}</p>
        @endif
        <small>لن تُحذف البيانات المحفوظة على الأجهزة، وستُستأنف المزامنة بعد إعادة التشغيل.</small>
    </main>
</body>
</html>
