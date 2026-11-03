# MOG Operations Platform — منصة العمليات الموحدة

لوحة إدارة جديدة ومستقلة لتطبيقات Flutter الثلاثة لبلدية غزة (تطبيق السائق، تطبيق مفتشي الآبار، تطبيق SCADA للمحابس).
بُنيت من الصفر بجانب الباك اند الحالي دون أي تعديل عليه: نفس قاعدة Oracle، نفس الصلاحيات، ولا تغيير في واجهات الموبايل.

| الوثيقة | المحتوى |
|---|---|
| [docs/01-audit.md](docs/01-audit.md) | تدقيق اللوحة الحالية، خريطة الوحدات، مشاكل تجربة الاستخدام |
| [docs/02-architecture.md](docs/02-architecture.md) | المعمارية المعلوماتية، التنقل، استراتيجية التطبيقات المتعددة، الصلاحيات |
| [docs/03-design-system.md](docs/03-design-system.md) | نظام التصميم (الألوان، الطباعة، RTL/LTR، المكونات) |
| [docs/04-roadmap.md](docs/04-roadmap.md) | الحالة، ما يحتاج متابعة على الخادم، خطة الإطلاق، مراجعة Ponytail، مقارنة graphify |
| [graphify-out/GRAPH_REPORT.md](graphify-out/GRAPH_REPORT.md) | خريطة الكود (graphify): الوحدات والعلاقات. افتح `graphify-out/graph.html` في المتصفح للعرض التفاعلي، وحدّثها بـ `graphify update .` |

## التشغيل المحلي (بدون Oracle)

```bash
composer install
npm install
cp .env.example .env   # ثم اضبط ORACLE_SQLITE_DATABASE إلى مسار مطلق داخل database/
php artisan key:generate
php artisan platform:demo   # يبني نسخة SQLite محلية من مخططات Oracle ببيانات عرض (محلي فقط)
npm run build
php artisan serve --port=8010
```

ثم افتح `http://127.0.0.1:8010/dev-login` (يتطلب `MOG_LOCAL_ADMIN_USER_ID=1` بعد `platform:demo`).

## الاختبارات

```bash
php artisan test
vendor/bin/pint --test
```

الاختبارات تعمل على SQLite في الذاكرة مع قواعد مرفقة تحاكي مخططات Oracle (`g_store`, `mog_users`, `tracking`, `eme`, `control_unit`).

## الإنتاج

راجع قسم "خطة الإطلاق" في [docs/04-roadmap.md](docs/04-roadmap.md). أهم نقطتين:
- لا يوجد `php artisan migrate`: المنصة لا تملك أي جدول في Oracle.
- `APP_TIMEZONE` يجب أن يطابق النظام القديم لأن الاثنين يقرآن نفس الطوابع الزمنية.
