# Fleet Management Backend — AI Agent Rules
> بلدية غزة | نظام إدارة الأسطول
> هذا الملف هو مرجع الذاكرة الأول لأي AI يعمل على هذا المشروع

## الهوية والسياق
- Laravel 11, PHP 8.2, Oracle DB (إنتاج), SQLite (تطوير)
- Admin Dashboard: Vue 3 + Inertia.js (SPA) — لا Ziggy، الروابط تُمرَّر عبر Shared Props
- Driver App: Flutter — Offline-first (لا يتطلب إنترنت أثناء الرحلة)
- Auth إدارة: MOG SSO (خارجي) — استخدم `Inertia::location()` وليس `redirect()->away()`
- Auth سائق: SMS OTP → Sanctum Token

## قواعد الكود (غير قابلة للتفاوض)
- Controllers رفيعة — المنطق في Services فقط
- Oracle → Repositories فقط (لا direct queries خارج Repositories)
- كل endpoint يتعامل مع الإنترنت المنقطع (Offline-first)
- لا تحذف رحلة محلية إلا بعد: حفظ + نقاط + عدد + checksum مؤكدة
- كل قرار إداري (approve/reject/clarify) يجب أن يكون قابلاً للتدقيق (auditable)
- استخدم FormRequest للمدخلات غير التافهة
- استخدم API Resources لعقود الاستجابة

## الملفات الجوهرية
- `app/Services/TripSyncService.php` — منطق مزامنة الرحلات
- `app/Repositories/MunicipalityVehicleRepository.php` — Oracle vehicles
- `app/Http/Controllers/AdminAuthController.php` — لوحة التحكم
- `app/Models/Trip.php` — نموذج الرحلة (يحتوي driver/vehicle accessors)
- `config/municipality.php` — أسماء جداول وأعمدة Oracle
- `docs/project-knowledge-base.md` — **التوثيق الكامل للمشروع**

## الجداول الرئيسية (محلي)
trips, trip_points, trip_stops, trip_passengers,
mission_locations, mission_types, audit_logs, sms_login_codes

## الجداول في Oracle (قراءة فقط)
VEHICLE (مركبات), BENEFICIARIES (سائقون), USERS (SSO)

## دورة حياة الرحلة
uploaded → (نقاط GPS) → confirmed → (مراجعة إدارية) → approved/rejected/needs_clarification

## قبل أي تعديل — اقرأ
docs/project-knowledge-base.md
