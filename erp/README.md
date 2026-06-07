# نظام ERP محاسبي — Laravel + React + MySQL

نظام ERP عام شامل، SaaS متعدد المستأجرين (قاعدة واحدة + `company_id`)، عربي/إنجليزي + RTL.
المرجع الكامل للمتطلبات: [`../docs/ERP_MASTER_PROMPT_AR.md`](../docs/ERP_MASTER_PROMPT_AR.md).

## الحالة
- ✅ **المرحلة 0 — التأسيس:** Laravel 13 + Sanctum + عزل مستأجر (Global Scope) + RBAC
  (أدوار/صلاحيات) + تسجيل دخول يُرجِع Token + واجهة React (دخول + /me) + Docker Compose.
- ✅ **المرحلة 1 — الإعدادات الأساسية:** CRUD كامل (معزول بالمستأجر + محمي بالصلاحيات)
  للفروع، السنوات المالية (+إقفال/فتح)، العملات (عملة أساسية واحدة)، مراكز التكلفة،
  أنواع السندات، الضرائب (معطّلة افتراضياً) + شاشات React + بيانات تجريبية.
- ✅ **المرحلة 2 — دليل الحسابات:** شجرة غير محدودة المستويات، نوع الحساب يحدّد طبيعته
  (مدين/دائن) وقائمته المالية تلقائياً (القواعد العالمية)، الأب تصنيف فقط ولا يقبل حركة،
  ترحيل الأرصدة Bottom-up، منع الدوائر وتطابق نوع الابن مع الأب، **تعدد الفئات**
  (مجموعات: نوع العميل/التابع له...)، **بيانات تفصيلية مرنة** لكل حساب (meta)،
  **استيراد شجرة قديمة** (معاينة بالأخطاء + تنفيذ) + شاشة شجرة في الواجهة.
- **الاختبارات:** 37 ناجحة (مصادقة + عزل + CRUD + قواعد محاسبية + شجرة + استيراد).

## التشغيل عبر Docker
```bash
cd erp
docker compose up --build
# API:  http://localhost:8000/api
# Web:  http://localhost:3000
```

## التشغيل المحلي (تطوير/اختبار)
```bash
# Backend
cd erp/backend
composer install
php artisan key:generate
php artisan migrate:fresh --seed   # افتراضي SQLite
php artisan serve                  # http://localhost:8000
php artisan test                   # تشغيل الاختبارات

# Frontend
cd erp/frontend
npm install && npm run dev         # http://localhost:3000
```

## بيانات الدخول التجريبية
| الشركة | البريد | كلمة المرور |
|--------|--------|-------------|
| مكتب النور للمحاماة | `admin@noor.test` | `password` |
| شركة الأمل التجارية | `admin@amal.test` | `password` |

## نقاط النهاية (المرحلة 0)
| الطريقة | المسار | الوصف |
|--------|--------|-------|
| POST | `/api/login` | تسجيل الدخول → Token |
| GET | `/api/me` | المستخدم الحالي + صلاحياته (محمي) |
| POST | `/api/logout` | إلغاء التوكن الحالي (محمي) |

### الإعدادات (المرحلة 1) — كلها تحت `/api/settings`
لكل كيان CRUD قياسي: `GET` (قائمة) · `GET/{id}` · `POST` · `PUT/{id}` · `DELETE/{id}`،
محمي بصلاحيات `settings.view/create/edit/delete`.

| الكيان | المسار |
|--------|--------|
| الفروع | `/api/settings/branches` |
| السنوات المالية | `/api/settings/fiscal-years` (+ `POST .../{id}/lock` و`/unlock`) |
| العملات | `/api/settings/currencies` |
| مراكز التكلفة | `/api/settings/cost-centers` |
| أنواع السندات | `/api/settings/voucher-types` |
| الضرائب | `/api/settings/tax-rates` |

## المعمارية
- **عزل المستأجر:** `App\Models\Concerns\BelongsToCompany` (Global Scope + ضبط `company_id`
  تلقائياً) + `App\Support\TenantContext` (يُضبط من `IdentifyCompany` middleware).
- **الصلاحيات:** `permission:<key>` middleware عبر `App\Models\User::hasPermission()`.
- **الأدوار النظامية:** مدير نظام، مدير مالي، محاسب، كاشير.
