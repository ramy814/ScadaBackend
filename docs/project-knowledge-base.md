# 🏛️ Fleet Management System — Knowledge Base
> **بلدية غزة | نظام إدارة الأسطول**
> آخر تحديث: 2026-05-19

---

## 1. نظرة عامة على المشروع

### ما هو النظام؟
نظام لتتبع رحلات مركبات بلدية غزة، يتكون من:
- **Backend (Laravel 11)**: API للسائقين + لوحة تحكم إدارية
- **Driver App (Flutter)**: تطبيق السائق — يعمل Offline-first
- **Admin Dashboard (Vue 3 + Inertia.js)**: مراجعة وإدارة الرحلات

### التقنيات الأساسية
| طبقة | التقنية |
|------|----------|
| Backend | Laravel 11, PHP 8.2 |
| Database | Oracle (إنتاج) + SQLite (تطوير) |
| Frontend Admin | Vue 3, Inertia.js, Vite |
| Driver App | Flutter (Offline-first) |
| PDF Reports | mPDF |
| Auth (SSO) | MOG SSO Package (بلدية) |
| SMS Auth | خدمة SMS داخلية |

---

## 2. هيكل قاعدة البيانات

### الجداول المحلية (SQLite/Oracle - managed by Laravel)

#### `trips` — الرحلات
```
id               — PK
local_id         — معرف الرحلة على جهاز السائق (idempotency key)
oracle_driver_id — معرف السائق في Oracle
driver_id        — FK (اختياري، يعتمد على وجود العمود)
driver_name      — اسم السائق (snapshot)
driver_phone     — هاتف السائق (snapshot)
driver_employee_number — رقم الموظف (snapshot)
oracle_vehicle_id — معرف المركبة في Oracle
vehicle_id       — FK (اختياري)
vehicle_plate    — لوحة المركبة (snapshot)
vehicle_name     — اسم المركبة (snapshot)
vehicle_type     — نوع المركبة (snapshot)
status           — [uploaded | confirmed]
review_status    — [pending | approved | rejected | needs_clarification]
review_note      — ملاحظة المراجع
reviewed_at      — تاريخ المراجعة
reviewed_by      — اسم المراجع
clarification_response         — رد السائق على طلب التوضيح
clarification_responded_at     — تاريخ الرد
started_at       — بداية الرحلة
ended_at         — نهاية الرحلة
confirmed_at     — تاريخ التأكيد
origin_type      — نوع نقطة الانطلاق
origin_id        — معرف نقطة الانطلاق
origin_text      — نص نقطة الانطلاق
destination_type — نوع الوجهة
destination_id   — معرف الوجهة
destination_text — نص الوجهة
mission_type     — نوع المهمة
notes            — ملاحظات السائق
start_odometer   — عداد المسافة عند البداية
end_odometer     — عداد المسافة عند النهاية
points_expected  — عدد نقاط GPS المتوقعة
points_received  — عدد نقاط GPS المستلمة
client_checksum  — checksum من التطبيق
server_checksum  — checksum محسوب على الخادم
wait_minutes     — دقائق الانتظار الإجمالية
virtual_fuel_liters — الوقود الافتراضي المحسوب (لتر)
```

#### `trip_points` — نقاط GPS
```
id
trip_id          — FK → trips
sequence_number  — رقم النقطة بالتسلسل (idempotency)
latitude         — خط العرض
longitude        — خط الطول
accuracy         — دقة GPS (متر)
speed            — السرعة (كم/س)
heading          — الاتجاه
battery_level    — مستوى البطارية
provider         — مزود GPS
captured_at      — وقت التقاط النقطة
```

#### `trip_stops` — التوقفات
```
id
trip_id
started_at       — بداية التوقف
ended_at         — نهاية التوقف
reason           — سبب التوقف
notes            — ملاحظات
```

#### `trip_passengers` — الركاب
```
id
trip_id
name             — اسم الراكب
employee_id      — الرقم الوظيفي (nullable)
source           — [free_text | employee_list | manual]
```

#### `mission_locations` — مواقع المهام
```
id
name             — اسم الموقع
type             — نوع الموقع
```

#### `mission_types` — أنواع المهام
```
id
name             — اسم نوع المهمة
```

#### `audit_logs` — سجل المراجعات
```
id
trip_id
action           — الإجراء (approved/rejected/clarification_requested)
note             — ملاحظة
performed_by     — منفذ الإجراء
created_at
```

#### `sms_login_codes` — رموز SMS
```
id
phone
code
expires_at
used_at
```

---

### الجداول في Oracle (للقراءة فقط - لا نكتب عليها)

> يتم الوصول إليها عبر Repositories فقط
> أسماء الجداول والأعمدة معرفة داخل Models/Repositories الخاصة بـ Oracle

#### `VEHICLE`
```
OID (id)             — معرف المركبة
PLATE_NUM (plate)    — رقم اللوحة
VEHICLE_NUM (name)   — اسم المركبة
vehicle_type         — نوع المركبة
BENEFICIARY_OID      — معرف السائق المرتبط بالمركبة
```

#### `BENEFICIARIES`
```
OID (id)             — معرف السائق
MOBILE_NUM (phone)   — رقم الجوال
NAME (name)          — الاسم
EMPLOYEE_NUM         — رقم الموظف
```

#### `USERS` (جدول SSO)
```
يُحدَّد بـ MOG_SSO_USERS_TABLE في .env
```

---

## 3. معمارية الكود (Architecture)

### مبادئ ثابتة
```
Controllers    → رفيعة (thin)، لا business logic
Services       → المنطق التجاري الأساسي
Repositories   → الوصول إلى Oracle فقط
FormRequests   → التحقق من المدخلات
API Resources  → عقود الاستجابة
```

### الـ Controllers

#### `AdminAuthController` (لوحة تحكم الإدارة)
- `showLogin()` → عرض صفحة الدخول
- `dashboard()` → إحصائيات الداشبورد + الرحلات الأخيرة
- `reports()` → تقارير الرحلات مع فلاتر
- `reportsCsv()` → تصدير CSV
- `reportsPdf()` → تصدير PDF (mPDF)
- `showTrip($trip)` → تفاصيل رحلة مع نقاط GPS
- `approve($trip)` → اعتماد رحلة
- `reject($trip)` → رفض رحلة
- `clarify($trip)` → طلب توضيح من السائق
- `logout()` → تسجيل خروج عبر SSO
- `locations()`, `storeLocation()`, `updateLocation()` → إدارة المواقع
- `missionTypes()`, `storeMissionType()`, `updateMissionType()` → إدارة أنواع المهام

#### `Api/TripSyncController` (API للسائق)
- `store()` → رفع بيانات رحلة جديدة
- `uploadPoints()` → رفع نقاط GPS (دفعات)
- `confirm()` → تأكيد اكتمال الرحلة + التحقق من الـ checksum

#### `Api/AuthController` (مصادقة السائق)
- SMS OTP Authentication

#### `Api/VehicleLookupController`
- البحث عن المركبات من Oracle

#### `Api/LocationLookupController`
- جلب مواقع المهام المتاحة

#### `Api/MissionTypeLookupController`
- جلب أنواع المهام المتاحة

#### `Api/BeneficiaryProfileController`
- ملف الموظف/السائق

---

### الـ Services

#### `TripSyncService` (الأهم)
```php
storeTrip(Beneficiary $driver, array $data): Trip
// يتحقق من مركبة Oracle → يحفظ snapshot كامل → يعيد استخدام idempotency key

uploadPoints(Trip $trip, array $points): int
// يحفظ نقاط GPS بـ sequence_number كـ idempotency key

confirm(Trip $trip, int $expected, string $checksum): Trip
// يحسب checksum الخادم → يقارنه بـ checksum السائق → يحدد 'confirmed' أو 'uploaded'
```

**قانون التأكيد:**
```
status = 'confirmed' إذا:
  - points_received >= points_expected
  - أو points_expected === 0 (رحلة بلا GPS تُؤكَّد تلقائياً)
```

**حساب checksum:**
```php
// تسلسل json_encode لمصفوفة من:
// [sequence_number (int), latitude (float), longitude (float), captured_at (ISO 8601)]
$serverChecksum = hash('sha256', json_encode($serverChecksumData));
```

#### `VirtualFuelService`
- يحسب الوقود الافتراضي من نقاط GPS (مسافة → لتر)

#### `SmsAuthService`
- توليد وإرسال والتحقق من رموز SMS

#### `CurrentBeneficiaryService`
- استخراج بيانات السائق الحالي من الـ session

---

### الـ Repositories

#### `MunicipalityVehicleRepository`
- `search(term, limit)` → البحث عن مركبات في Oracle
- `findByPlate(plate)` → إيجاد مركبة برقم اللوحة
- `findByDriverOracleId(id)` → مركبات مرتبطة بالسائق عبر OID
- `findByDriverMobile(phone)` → مركبات مرتبطة بالسائق عبر الجوال (مع normalization للأرقام)
- `findAssignedToDriver(id, phone, plate)` → يجمع الطريقتين ويتحقق من اللوحة

#### `MunicipalityDriverRepository`
- بيانات السائق من Oracle

#### `OracleReadiness`
- `available()` → هل Oracle متاح؟ (يستخدم قبل أي query على Oracle)

---

## 4. نظام المصادقة

### مصادقة السائق (Driver App)
```
SMS OTP → 6 أرقام → صلاحية 5 دقائق → API Token (Sanctum)
```

### مصادقة الإدارة (Admin Dashboard)
```
MOG SSO (خارجي) → SAML/OAuth → Session
Guard: 'admin'
```

**هام:** عند انتهاء الجلسة أو انقضاء الـ SSO:
```php
// استخدم Inertia::location() وليس redirect()->away()
// لأن Inertia يرسل XHR requests وليس Full Page Navigation
return Inertia::location($ssoUrl);
```

---

## 5. Frontend (Vue 3 + Inertia.js)

### مبدأ الروابط — بدون Ziggy
```
// الروابط تُمرَّر من الـ Backend عبر HandleInertiaRequests
// في كل Page Component: $page.props.urls.dashboard
// أو في script: usePage().props.urls.xxx
```

### الـ Shared Props (HandleInertiaRequests)
```js
flash.ok      // رسائل النجاح
flash.error   // رسائل الخطأ
auth.user     // معلومات المدير المسجل
urls: {
  dashboard, locations, missionTypes,
  reports, reportsCsv, reportsPdf,
  logout, trips_base
}
```

### صفحات Vue
```
Pages/Admin/
  Dashboard.vue    — الداشبورد الرئيسي
  TripShow.vue     — تفاصيل رحلة + خريطة Leaflet
  Reports.vue      — تقارير + تصدير
  Locations.vue    — إدارة المواقع
  MissionTypes.vue — إدارة أنواع المهام

Layouts/
  AppLayout.vue    — القالب الرئيسي + Navbar
```

### Trip.driver و Trip.vehicle في Frontend
```php
// في Trip Model يوجد accessor محمي بـ $appends
protected $appends = ['driver', 'vehicle'];

// trip.vehicle.plate_number في Vue
// trip.driver.name في Vue
```

---

## 6. دورة حياة الرحلة (Trip Lifecycle)

```
[السائق يبدأ رحلة]
    ↓ POST /api/trips → TripSyncController@store
    → status: 'uploaded'

[السائق يرفع نقاط GPS - دفعات]
    ↓ POST /api/trips/{trip}/points → uploadPoints
    → points_received يزداد

[السائق ينهي الرحلة]
    ↓ POST /api/trips/{trip}/confirm → confirm
    → يُحسب checksum الخادم
    → status: 'confirmed' (أو 'uploaded' إذا ناقصت نقاط)
    → يُحسب virtual_fuel_liters

[المدير يراجع]
    ↓ approve → review_status: 'approved'
    ↓ reject  → review_status: 'rejected'
    ↓ clarify → review_status: 'needs_clarification'

[السائق يرد على التوضيح]
    ↓ POST /api/trips/{trip}/clarification-response
    → clarification_response, clarification_responded_at
```

---

## 7. قواعد Offline-First

> **المبدأ الذهبي:** التطبيق يعمل بدون إنترنت أثناء الرحلة

1. لا endpoint يتطلب اتصالاً حياً أثناء الرحلة
2. لا تحذف رحلة محلية إلا بعد تأكيد حفظها على الخادم مع:
   - حفظ الرحلة الأساسية ✓
   - رفع جميع الدفعات ✓
   - التحقق من العدد ✓
   - التحقق من الـ checksum ✓
3. كل نقطة GPS لها `sequence_number` يضمن idempotency عند إعادة الرفع
4. `local_id` في الرحلة يضمن عدم التكرار عند إعادة المزامنة

---

## 8. إعدادات البيئة المهمة

```env
DB_CONNECTION=sqlite    # للتطوير
# أو
DB_CONNECTION=oracle    # للإنتاج

ORACLE_HOST=...
ORACLE_PORT=1521
ORACLE_DATABASE=...
ORACLE_USERNAME=...
ORACLE_PASSWORD=...

MOG_SSO_USERS_TABLE=USERS   # جدول المستخدمين في Oracle SSO
```

## 9. API للسائق (Driver App)

### Base URL
```
/api/
```

### Authentication
```
Bearer Token (Sanctum)
Header: Authorization: Bearer {token}
```

### المسارات الأساسية

| Method | URL | الوظيفة |
|--------|-----|----------|
| POST | `/api/auth/send-code` | إرسال رمز SMS |
| POST | `/api/auth/verify-code` | التحقق من الرمز + الحصول على Token |
| GET | `/api/vehicles` | قائمة المركبات المتاحة |
| GET | `/api/vehicles/search` | البحث عن مركبة |
| GET | `/api/locations` | مواقع المهام |
| GET | `/api/mission-types` | أنواع المهام |
| POST | `/api/trips` | إنشاء رحلة جديدة |
| POST | `/api/trips/{id}/points` | رفع نقاط GPS |
| POST | `/api/trips/{id}/confirm` | تأكيد اكتمال الرحلة |
| POST | `/api/trips/{id}/clarification-response` | الرد على طلب توضيح |

### مثال على request رفع رحلة
```json
{
  "local_id": "uuid-generated-on-device",
  "vehicle_plate": "ABC-1234",
  "started_at": "2026-05-19T08:00:00Z",
  "ended_at": "2026-05-19T09:30:00Z",
  "origin": { "type": "location", "id": 5, "text": "مقر البلدية" },
  "destination": { "type": "free_text", "text": "مستشفى الشفاء" },
  "mission_type": "صيانة",
  "notes": "ملاحظة السائق",
  "wait_minutes": 15,
  "points_expected": 100,
  "start_odometer": 50000,
  "end_odometer": 50045,
  "passengers": [
    { "name": "أحمد محمد", "employee_id": "1234", "source": "employee_list" }
  ],
  "stops": [
    { "started_at": "2026-05-19T08:30:00Z", "ended_at": "2026-05-19T08:45:00Z", "reason": "وقود" }
  ]
}
```

---

## 10. نمط Phone Normalization للمركبات

عند البحث عن مركبة برقم الهاتف، يُجرب النظام تلقائياً جميع الصيغ:
```
0599123456   → أصلي
0599123456   → أرقام فقط
599123456    → بدون 0
970599123456 → مع 970
0599123456   → التحويل من 970
last 9 digits comparison (ORACLE SUBSTR)
```

---

## 11. قواعد المراجعة الإدارية

```
review_status:
  pending               → لم تُراجع بعد (default)
  approved              → مُعتمدة (نهائي - لا يمكن التغيير)
  rejected              → مرفوضة (نهائي - لا يمكن التغيير)
  needs_clarification   → بانتظار رد السائق (يمكن الموافقة/الرفض بعد الرد)
```

**قاعدة:** بعد `approved` أو `rejected` لا يمكن تغيير الحالة من الداشبورد.

---

## 12. Git & Deployment

### المستودع
```
http://172.17.80.51:3000/rami.gh/Tracking-Veichle-BackEnd.git
Branch: main
```

### ملفات مُرفقة في Git
```
public/build/     — ملفات Frontend المبنية (مُتتبَّعة بـ -f)
```

### سير نشر التحديثات
```bash
# محلياً
npm run build
git add -f public/build
git add .
git commit -m "..."
git push

# على السيرفر
git pull origin main
composer install
php artisan migrate
```

---

## 13. الأخطاء الشائعة وحلولها

| الخطأ | السبب | الحل |
|-------|--------|------|
| SSO redirect لا يعمل في SPA | Inertia يرسل XHR | استخدم `Inertia::location()` بدلاً من `redirect()->away()` |
| `driver` و`vehicle` لا يظهران في JSON | Accessors لا تُضمَّن تلقائياً | أضف `$appends = ['driver', 'vehicle']` في Trip Model |
| `route()` غير معرّفة في Vue | Ziggy محذوفة | استخدم `$page.props.urls.xxx` |
| composer install يفشل بـ PHP version | الحزم تتطلب 8.3 | استخدم Laravel 11 + yajra ^11 |
| مركبة لا تُوجد رغم وجودها | اختلاف صيغة الهاتف | نظام Phone Normalization يتولى ذلك تلقائياً |
| نقاط GPS ناقصة | الإرسال انقطع | `local_id` + `sequence_number` تضمن الإرسال الآمن مرة أخرى |

---

## 14. أولويات التطوير القادمة

1. **Oracle Schema نهائي** → ترحيل Repository Pattern للجداول الإنتاجية
2. **تحسين Loading States** في Vue عند approve/reject
3. **اختبار PDF** مع بيانات حقيقية (mPDF + العربية)
4. **نظام إشعارات** للسائق عند رد الإدارة على الرحلة
5. **تقرير الوقود الافتراضي** الشهري مع مقارنة بالاستهلاك الفعلي

---

*هذا الملف هو قاعدة معرفة المشروع — يجب تحديثه عند كل تغيير جوهري في البنية أو قاعدة البيانات أو الـ API.*
