# Fleet Management Backend — AI Agent Rules
> بلدية غزة | نظام إدارة الأسطول
> هذا الملف هو مرجع الذاكرة الأول لأي AI يعمل على هذا المشروع

## قاعدة المعرفة الشاملة للذكاء الاصطناعي (AI Knowledge Base)
لقد تم بناء مجلد مخصص لك كذكاء اصطناعي يحتوي على كافة تفاصيل المشروع. **يجب عليك قراءة الملفات في مجلد `.ai/` قبل اتخاذ أي إجراء معماري أو كتابة كود معقد.**

مجلد `.ai/` يحتوي على:
- `.ai/project-overview.md`: نظرة عامة
- `.ai/architecture.md`: المعمارية وفصل المهام
- `.ai/coding-rules.md`: قواعد كتابة الكود
- `.ai/api-conventions.md`: مسارات الـ API للـ Sync
- `.ai/database/`: قواعد البيانات والجداول
- `.ai/workflows/`: دورات الحياة (مثل المراجعة)
- `.ai/prompts/`: قواعد مخصصة (مثل Flutter)

## الهوية والسياق
- Laravel 11, PHP 8.2, Oracle DB (إنتاج), SQLite (تطوير)
- Admin Dashboard: Vue 3 + Inertia.js (SPA) — لا Ziggy، الروابط تُمرَّر عبر Shared Props
- Driver App: Flutter — Offline-first (لا يتطلب إنترنت أثناء الرحلة)
- Auth إدارة: MOG SSO (خارجي) — استخدم `Inertia::location()` وليس `redirect()->away()`
- Auth سائق: SMS OTP → Sanctum Token

## قواعد الكود الأساسية (اقرأ .ai/coding-rules.md للتفاصيل)
- Controllers رفيعة — المنطق في Services فقط
- Oracle → Repositories فقط (لا direct queries خارج Repositories)
- كل endpoint يتعامل مع الإنترنت المنقطع (Offline-first)
- لا تحذف رحلة محلية إلا بعد: حفظ + نقاط + عدد + checksum مؤكدة

## الملفات الجوهرية
- `app/Services/TripSyncService.php` — منطق مزامنة الرحلات
- `app/Repositories/MunicipalityVehicleRepository.php` — Oracle vehicles
- `app/Models/Trip.php` — نموذج الرحلة (يحتوي driver/vehicle accessors)

## الجداول الرئيسية (اقرأ .ai/database/tables.md للتفاصيل)
- **محلي**: trips, trip_points, trip_stops, audit_logs
- **Oracle**: VEHICLE (مركبات), BENEFICIARIES (سائقون)

## قبل البدء في أي ميزة جديدة
ابحث في مجلد `.ai/` عن الملف المناسب للمهمة واقرأه.

## Strict AI Directives
Always read all `.ai` markdown files first.

**Never:**
- bypass service layer
- place logic inside controllers
- write direct SQL in controllers

**Always:**
- follow repository pattern
- use existing response wrapper
- preserve Oracle compatibility
- follow existing naming conventions
