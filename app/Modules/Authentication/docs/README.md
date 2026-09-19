# Authentication & Users Module

> Module: `app/Modules/Authentication/` + `app/Modules/Users/`
>
> Last updated: 2026-09-02

---

## 1. Overview

هذا الـModule مسؤول عن **المصادقة** (Authentication) وإدارة **مستخدمي النظام** داخل **Distribution & Inventory Management System**.

يهدف إلى:

- تسجيل دخول المستخدمين (Email + Password).
- إدارة المستخدمين (إنشاء / تحديث / تعطيل / تفعيل).
- استرجاع كلمة المرور وإعادة تعيينها.
- تغيير كلمة المرور.
- إدارة الأدوار والصلاحيات (Role + Permission).
- تطبيق عزل البيانات بين الموزعين (Data Isolation).
- تسجيل سجل الدخول (Login History) والعمليات الحساسة (Audit Log).

---

## 2. Scope

### داخل الـModule (In Scope)

| Feature | Status |
|---|---|
| Login (Email + Password) | ✅ |
| Logout (جهاز واحد / جميع الأجهزة) | ✅ |
| Get Current User (`/me`) | ✅ |
| Change Password (مصادق) | ✅ |
| Forgot Password / Reset Password | ✅ |
| User CRUD | ✅ |
| User Status (تفعيل / تعطيل) | ✅ |
| Roles & Permissions | ✅ |
| Distributor Data Isolation | ✅ |
| Login History | ✅ |
| Audit Log | ✅ |
| Failed Login Protection (Lockout) | ✅ |
| Password Policy | ✅ |
| Temporary Password على أول تسجيل | ✅ |

### خارج الـModule (Out of Scope)

- إدارة العملاء/المنتجات/المخزون/الطلبات (Modules منفصلة).
- Business Dashboard.
- Notifications خارج نطاق Auth.

---

## 3. Actors

| Actor | الوصف |
|---|---|
| **Super Admin** | جميع الصلاحيات، إدارة المستخدمين والصلاحيات. |
| **Admin** | عمليات إدارية يومية حسب الصلاحيات. |
| **Distributor** | الوصول إلى بياناته فقط (عزلة تامة). |
| **Customer Service** | Read-Only على العملاء والموزعين. |
| **Guest / Unauthenticated** | تسجيل الدخول / استرجاع كلمة المرور فقط. |

---

## 4. Architecture (Modular Monolith)

```
Authentication/
├── Actions/          (اختياري - للـFlows القصيرة)
├── Controllers/
│   └── AuthController.php
├── Enums/
│   └── LoginEvent.php
├── Exceptions/
│   ├── AuthenticationException.php
│   ├── InvalidCredentialsException.php
│   ├── AccountDeactivatedException.php
│   ├── TooManyLoginAttemptsException.php
│   ├── CurrentPasswordIncorrectException.php
│   ├── InvalidPasswordResetTokenException.php
│   └── TemporaryPasswordRequiredException.php
├── Models/
│   └── LoginHistory.php
├── Policies/
│   └── UserPolicy.php
├── Requests/
│   ├── LoginRequest.php
│   ├── UpdatePasswordRequest.php
│   ├── ForgotPasswordRequest.php
│   └── ResetPasswordRequest.php
├── Resources/
│   └── UserResource.php
├── Services/
│   ├── AuthService.php
│   └── PasswordResetService.php
├── routes.php
└── docs/
    ├── README.md
    └── API.md
```

**ملاحظة:** `User` Model يبقى في `app/Models/User.php` لكونه الكيان المركزي (Shared Entity), ويُستخدم عبر كل الـModules.

---

## 5. User Model

الكيان الأساسي `App\Models\User`:

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Full Name |
| `username` | string, nullable, unique | Internal identifier (ليس وسيلة Login) |
| `email` | string, unique | **وسيلة Login الوحيدة** |
| `phone` | string, nullable | بيانات تواصل فقط |
| `password` | hashed | Mutator يحفظ Hash تلقائياً |
| `role` | string (UserRole enum) | Super Admin / Admin / Distributor / Customer Service |
| `is_active` | boolean | تفعيل/تعطيل الحساب |
| `must_change_password` | boolean | إجبار تغيير كلمة المرور المؤقتة |
| `failed_login_attempts` | integer | عدّاد محاولات الدخول الفاشلة |
| `locked_until` | timestamp nullable | وقت انتهاء القفل |
| `email_verified_at` | timestamp | |
| `timestamps` | | |

### العلاقات

```text
User 1 ──────── 1 Distributor   (hasOne distributor)
User 1 ──────── n Order         (hasMany orders)
```

### Helpers

- `role(): ?UserRole` — يرجع الـRole كـEnum.
- `hasPermission(string $permission): bool` — فحص صلاحية.
- `isSuperAdmin() / isAdmin() / isDistributor() / isCustomerService()`.
- `isActive(): bool`.

---

## 6. Roles & Permissions

### Roles (V1) — وفق BRD

```php
UserRole::SUPER_ADMIN        // 'super_admin'
UserRole::ADMIN              // 'admin'
UserRole::DISTRIBUTOR        // 'distributor'
UserRole::CUSTOMER_SERVICE   // 'customer_service'
```

### الـRole الوحيد الافتراضي في V1

كل User يملك **Role واحدة فقط**.

### الصلاحيات (Module + Action)

كل صلاحية بصيغة `module.action`، مثال:

```text
users.view
users.create
users.update
users.delete
products.view
inventory.adjust
orders.approve
```

أسماء الصلاحيات **Stable** ولا تعتمد على اللغة.

---

## 7. Authorization

Authorization يتم عبر **Laravel Policies + Gates**, وليس عبر `if ($user->role === 'admin')`.

### Gates

في `AuthServiceProvider::registerPermissionGates()`:

- يتم تعريف **Gate لكل صلاحية** موجودة في `UserRole::permissions()`.
- `Gate::before` يسمح فقط بـ **Super Admin** (الصلاحيات الكاملة) — بدون تجاوز أي Policy.
- أي مستخدم آخر يمر عبر الـGates/Policies الحقيقية.

### Policies

الـPolicies المسجلة:

| Model | Policy |
|---|---|
| `User` | `UserPolicy` |
| `Distributor` | `DistributorPolicy` |
| `Customer` | `CustomerPolicy` |
| `Invoice` | `InvoicePolicy` |
| `Payment` | `PaymentPolicy` |
| `Order` | `OrderPolicy` |

### Data-Level Authorization (Distributor Isolation)

- **Distributor** يرى بيانات **موزّعه فقط**.
- في `CustomerPolicy` / `DistributorPolicy`:
  - `$user->distributor?->id === $customer->distributor_id`.
- Filtering Server-Side في الـServices (مثل `CustomerService::getAll()`).

**قاعدة: Frontend visibility ليست Authorization.** كل التحقق في Backend.

---

## 8. Authentication Flow

### Login Flow

```text
User enters Email + Password
        ↓
VALIDATION (LoginRequest)
        ↓
AuthService::login()
        ↓
Check User exists + Hash::check
        ↓
Check Failed-Login Lockout
        ↓
Check is_active
        ↓
Create Sanctum Token (24h)
        ↓
Record LoginHistory + Audit Log
        ↓
Return UserResource + token
```

### Failed Login Protection

```text
MAX_FAILED_ATTEMPTS = 5
LOCKOUT_MINUTES     = 15
```

- عند 5 محاولات فاشلة → قفل الحساب لمدة 15 دقيقة.
- عند النجاح → إعادة تصفير العداد.
- رمي `TooManyLoginAttemptsException` (HTTP 429).

---

## 9. Password Management

### Password Policy (`StrongPassword` Rule)

```text
- 8 Characters على الأقل
- حرف Capital واحد على الأقل
- حرف Small واحد على الأقل
- رقم واحد على الأقل
- Special Character واحد على الأقل
```

### Change Password (مصادق)

- `GET/PUT /auth/password`.
- يتطلب `current_password` + `password` + `password_confirmation`.
- يرمي `CurrentPasswordIncorrectException` (HTTP 422).

### Forgot Password

- `POST /auth/forgot-password` — يرسل رابط إعادة التعيين.
- **دائماً** يرجع Success لتجنب User Enumeration.
- يستخدم `Password::sendResetLink`.

### Reset Password

- `POST /auth/reset-password` — يتحقق من الـtoken ويحدّث كلمة المرور.
- يرمي `InvalidPasswordResetTokenException` (HTTP 400) عند token غير صالح.
- يسجّل `PASSWORD_RESET` في LoginHistory.

### Temporary Password

- عند إنشاء User بواسطة Super Admin، `must_change_password = true`.
- عند الـLogin، الـResponse يتضمن `must_change_password: true`.
- لا يمنع الـLogin لكن الـFrontend يفرض تغيير كلمة المرور.

---

## 10. Security

| Concern | Implementation |
|---|---|
| Password Hashing | `$casts = ['password' => 'hashed']` في User Model |
| No Public Registration | لا يوجد أي Route للتسجيل |
| Token Auth | Laravel Sanctum (Bearer Token) |
| Token Expiry | 24 ساعة |
| Disabled Account Lock | Middleware `active` + Login Check |
| Token Revocation عند التعطيل | `EnsureUserIsActive` + `UserService::toggleStatus` |
| Failed Login Protection | `failed_login_attempts` + `locked_until` |
| Rate Limiting | `throttle:5,1` على Login / Forgot / Reset |
| Login History | جدول `login_histories` |
| Audit Log | spatie/laravel-activitylog |
| No Secret Logging | لا نسجّل Passwords/Tokens |
| Localization | `lang/en` + `lang/ar` (Translation Keys) |
| Central Error Handling | Business Exceptions مع `error_code` + `http_status` |

---

## 11. Business Rules (من BRD)

1. لا يوجد Public Registration.
2. Login فقط بـ Email + Password.
3. Email فريد لكل مستخدم.
4. Phone ليس وسيلة Login في V1.
5. Username ليس وسيلة Login في V1.
6. كل User يملك Role واحدة فقط.
7. كل Distributor مرتبط بحساب User واحد.
8. كل User دور Distributor مرتبط بـ Distributor واحد فقط.
9. إنشاء المستخدمين فقط بواسطة Super Admin (أو من لديه `users.create`).
10. المستخدم المعطّل لا يستطيع تسجيل الدخول.
11. تعطيل الحساب لا يحذف البيانات التاريخية.
12. Password تُخزّن Hash دائماً.
13. الصلاحيات تعتمد على Role + Permissions.
14. Approval صلاحيّة مستقلة.
15. العمليات الحساسة تُسجّل في Audit Log.
16. Forgot Password / Reset مدعوم.
17. Failed Login Protection (5 محاولات → قفل 15 دقيقة).
18. Session/Token ينتهي بعد 24 ساعة.
19. Distributor Data Isolation مضمّنة.
20. لا حذف دائم للمستخدم في العمليات الطبيعية (يُفضَّل Deactivate).

---

## 12. Edge Cases

| Case | Handling |
|---|---|
| معطّل يحاول الدخول | `AccountDeactivatedException` + إلغاء الـtoken |
| 5 محاولات فاشلة | قفل 15 دقيقة (`TooManyLoginAttemptsException`) |
| مستخدم بدون Distributor ودوره Distributor | لا يرى بيانات موزّع |
| Distributor يحاول فتح عملاء موزّع آخر | 403 عبر `CustomerPolicy` |
| Token منتهي / غير صالح | 401 `UNAUTHENTICATED` |
| Reset token منتهي | 400 `INVALID_RESET_TOKEN` |
| إنشاء Super Admin بواسطة Admin | ممنوع في `UserPolicy::create/update` |
| حذف المستخدم نفسه | ممنوع في `UserPolicy::delete` |
| تعطيل ثم إعادة تفعيل | تظل البيانات، تُنشأ token جديدة |

---

## 13. API Endpoints Summary

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/auth/login` | Public | تسجيل الدخول |
| GET | `/api/v1/auth/me` | Sanctum+active | بيانات المستخدم الحالي |
| POST | `/api/v1/auth/logout` | Sanctum+active | خروج |
| POST | `/api/v1/auth/logout-all` | Sanctum+active | خروج من كل الأجهزة |
| PUT | `/api/v1/auth/password` | Sanctum+active | تغيير كلمة المرور |
| POST | `/api/v1/auth/forgot-password` | Public | إرسال رابط إعادة التعيين |
| POST | `/api/v1/auth/reset-password` | Public | إعادة تعيين كلمة المرور |
| GET | `/api/v1/users` | Sanctum+active | قائمة المستخدمين |
| POST | `/api/v1/users` | Sanctum+active | إنشاء مستخدم |
| GET | `/api/v1/users/{user}` | Sanctum+active | عرض مستخدم |
| PUT | `/api/v1/users/{user}` | Sanctum+active | تحديث مستخدم |
| DELETE | `/api/v1/users/{user}` | Sanctum+active | حذف مستخدم |
| POST | `/api/v1/users/{user}/toggle-status` | Sanctum+active | تفعيل/تعطيل |

> **ملاحظة:** كل الـRoutes تبدأ بـ `/api/v1/` وفق Convention الـVersioning.

---

## 14. Test Plan (Pest)

ينبغي تغطيتها (Feature Tests):

- `LoginTest` — نجاح/فشل/حساب معطّل/قفل.
- `PasswordTest` — تغيير كلمة المرور + سياسة القوة.
- `ForgotPasswordTest` — إرسال الرابط + User Enumeration.
- `ResetPasswordTest` — reset ناجح + token فاسد.
- `UserManagementTest` — CRUD + Authorization + تعطيل.
- `AuthorizationTest` — Gates / Policies / Data Isolation.
- `LocalesTest` — الردود تختلف حسب اللغة لكن `error_code` ثابت.

---

## 15. API Documentation

يتم توثيق الـAPI عبر **Scramble**:

```bash
php artisan scramble:analyze
php artisan scramble:export openapi.json
```

التوثيق التفصيلي لكل Endpoint مع الطلبات والاستجابات موجود في:

- [API.md](./API.md)
- والـSwagger UI الافتراضي من Scramble في `/docs/api`.

---

## 16. Approved Stack المستخدم

```text
Laravel / PHP
Laravel Sanctum
Laravel Policies + Gates
spatie/laravel-activitylog
Scramble
Pest PHP
Laravel Pint
```

لا تمّت إضافة أي Package جديد خارج الـApproved Stack في AGENTS.md.

---

## 17. Follow-ups / Next Steps

- [ ] إنشاء Seeder للأدوار والصلاحيات (Super Admin أولاً).
- [ ] ربط Notifications فعلية لرسائل الـReset (قناة بريد).
- [ ] إضافة `must_change_password` flow متكامل في الـFrontend.
- [ ] بناء Feature Tests كاملة حسب خطة الاختبار.
- [ ] تقرير MySQL Migration نظيف وتشغيل `migrate:fresh --seed`.
