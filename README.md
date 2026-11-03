# MOG Operations Platform — منصة العمليات الموحدة

لوحة الإدارة وواجهات API لتطبيقات Flutter الثلاثة لبلدية غزة (تطبيق السائق، تطبيق مفتشي الآبار، تطبيق SCADA للمحابس).
حلّت محل الباك اند القديم في نفس المستودع: نفس قاعدة Oracle، نفس الصلاحيات، نفس عناوين الـ API والتوكنات، فلا تحتاج التطبيقات أي تحديث.
كود النظام القديم محفوظ في تاريخ git، ووثائقه في `.ai/` و`docs/legacy/`.

| الوثيقة | المحتوى |
|---|---|
| [docs/01-audit.md](docs/01-audit.md) | تدقيق اللوحة الحالية، خريطة الوحدات، مشاكل تجربة الاستخدام |
| [docs/02-architecture.md](docs/02-architecture.md) | المعمارية المعلوماتية، التنقل، استراتيجية التطبيقات المتعددة، الصلاحيات |
| [docs/03-design-system.md](docs/03-design-system.md) | نظام التصميم (الألوان، الطباعة، RTL/LTR، المكونات) |
| [docs/04-roadmap.md](docs/04-roadmap.md) | الحالة، ما يحتاج متابعة على الخادم، خطة الإطلاق، مراجعة Ponytail، مقارنة graphify |
| [docs/05-cutover-plan.md](docs/05-cutover-plan.md) | خطة الاستغناء عن النظام القديم: ما بقي (واجهات الموبايل)، الشروط الحاجبة، المراحل والرجوع |
| [graphify-out/GRAPH_REPORT.md](graphify-out/GRAPH_REPORT.md) | خريطة الكود (graphify): الوحدات والعلاقات. افتح `graphify-out/graph.html` في المتصفح للعرض التفاعلي، وحدّثها بـ `graphify update .` |

## التشغيل المحلي (بدون Oracle)

```bash
composer install
npm install
cp .env.example .env   # ثم اضبط ORACLE_SQLITE_DATABASE إلى مسار مطلق داخل database/
php artisan key:generate
php artisan platform:demo   # يبني نسخة SQLite محلية من مخططات Oracle ببيانات عرض (محلي فقط)
npm run build            # محلياً (public/build، غير مرفوع)
php artisan serve --port=8010
```

ثم افتح `http://127.0.0.1:8010/dev-login` (يتطلب `MOG_LOCAL_ADMIN_USER_ID=1` بعد `platform:demo`).

## الاختبارات

```bash
php artisan test
vendor/bin/pint --test
```

الاختبارات تعمل على SQLite في الذاكرة مع قواعد مرفقة تحاكي مخططات Oracle (`g_store`, `mog_users`, `tracking`, `eme`, `control_unit`).

## الإنتاج (النشر عبر Gitea)

الخادم يسحب فرع `main` من Gitea تلقائياً؛ لا يشغّل `migrate` (ونفترض أنه لا يشغّل `composer`/`npm`). لذلك:
- قبل كل رفع: `php artisan test` و`vendor/bin/pint --test` و`npm run build:release`، ثم ارفع مجلد `public/build-platform` مع الكود.
- لا يوجد `php artisan migrate`: المنصة لا تملك أي جدول في Oracle، ومجلد `database/migrations` تاريخ النظام القديم فقط.
- ملفات الخادم المحلية (`.env`، `config/app.php`، `config/database.php`، `config/filesystems.php`، `config/session.php`، `composer.lock`) لا تُرفع أبداً؛ المنصة تعمل بها كما هي.
- خطوات الاستبدال والرجوع: [docs/05-cutover-plan.md](docs/05-cutover-plan.md) القسم 8.
