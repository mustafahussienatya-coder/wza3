# Authentication & Users — API Documentation

> جميع الـEndpoints تبدأ بـ `/api/v1/`.
> الـAuthentication عبر `Authorization: Bearer <token>` (Sanctum).
> كل الردود بصيغة JSON وتتبع **API Response Contract** في AGENTS.md.

---

## Response Contract

### Success — Single Resource

```json
{
    "success": true,
    "message": "Login successful.",
    "data": {}
}
```

### Success — Collection

```json
{
    "success": true,
    "data": [],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 0
    },
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": null
    }
}
```

### Error

```json
{
    "success": false,
    "message": "Invalid credentials.",
    "error_code": "INVALID_CREDENTIALS",
    "errors": {}
}
```

> الـ`error_code` ثابت ولا يتغير حسب اللغة. الـ`message` يتغير حسب اللغة (ar / en).

---

## Endpoints

---

### 1. Login

`POST /api/v1/auth/login`

**Authentication:** Public (لا تتطلب token)

**Request Body:**

```json
{
    "email": "admin@example.com",
    "password": "Password1!"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| `email` | required, email, max:255 |
| `password` | required, string |

**Success Response — `200`:**

```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "user": {
            "id": 1,
            "name": "Admin",
            "email": "admin@example.com",
            "role": "super_admin",
            "permissions": ["users.view", "users.create", "..."],
            "is_active": true,
            "must_change_password": false
        },
        "token": "1|abcdef...",
        "token_type": "Bearer",
        "expires_at": "2026-09-03T10:00:00+00:00",
        "must_change_password": false
    }
}
```

**Error Responses:**

| Status | error_code | Description |
|---|---|---|
| 401 | `INVALID_CREDENTIALS` | بيانات الدخول خاطئة |
| 403 | `ACCOUNT_DEACTIVATED` | الحساب معطّل |
| 422 | `VALIDATION_ERROR` | فشل التحقق |
| 429 | `TOO_MANY_LOGIN_ATTEMPTS` | قفل بسبب المحاولات الفاشلة |

---

### 2. Get Current User

`GET /api/v1/auth/me`

**Authentication:** Bearer Token + Active

**Success Response — `200`:**

```json
{
    "success": true,
    "message": "User retrieved successfully.",
    "data": {
        "id": 1,
        "name": "Admin",
        "email": "admin@example.com",
        "username": null,
        "role": "super_admin",
        "role_label": "Super Admin",
        "permissions": ["users.view", "..."],
        "phone": null,
        "is_active": true,
        "must_change_password": false
    }
}
```

**Error Responses:**

| Status | error_code |
|---|---|
| 401 | `UNAUTHENTICATED` |
| 403 | `ACCOUNT_DEACTIVATED` |

---

### 3. Logout

`POST /api/v1/auth/logout`

**Authentication:** Bearer Token + Active

**Success Response — `200`:**

```json
{
    "success": true,
    "message": "Logged out successfully."
}
```

يُلغى الـCurrent Token.

---

### 4. Logout from All Devices

`POST /api/v1/auth/logout-all`

**Authentication:** Bearer Token + Active

**Success Response — `200`:**

```json
{
    "success": true,
    "message": "Logged out from all devices."
}
```

يُلغى **جميع** Tokens الخاصة بالمستخدم.

---

### 5. Update Password (Authenticated)

`PUT /api/v1/auth/password`

**Authentication:** Bearer Token + Active

**Request Body:**

```json
{
    "current_password": "OldPassword1!",
    "password": "NewPassword1!",
    "password_confirmation": "NewPassword1!"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| `current_password` | required, string |
| `password` | required, confirmed, StrongPassword (8+ / A-Z / a-z / 0-9 / special) |

**Success Response — `200`:**

```json
{
    "success": true,
    "message": "Password updated successfully."
}
```

**Error Responses:**

| Status | error_code | Description |
|---|---|---|
| 401 | `UNAUTHENTICATED` | token غير صالح |
| 422 | `CURRENT_PASSWORD_INCORRECT` | كلمة المرور الحالية خاطئة |
| 422 | `VALIDATION_ERROR` | فشل سياسة القوة أو confirmation |

---

### 6. Forgot Password

`POST /api/v1/auth/forgot-password`

**Authentication:** Public

**Request Body:**

```json
{
    "email": "admin@example.com"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| `email` | required, email, max:255 |

**Success Response — `200` (دائماً):**

```json
{
    "success": true,
    "message": "If the email exists, a password reset link has been sent."
}
```

> دائماً ترجع Success لمنع معرفة وجود البريد (User Enumeration Prevention).
> إذا كان الحساب معطّلاً → `403 ACCOUNT_DEACTIVATED`.

---

### 7. Reset Password

`POST /api/v1/auth/reset-password`

**Authentication:** Public

**Request Body:**

```json
{
    "email": "admin@example.com",
    "token": "reset-token-from-email",
    "password": "NewPassword1!",
    "password_confirmation": "NewPassword1!"
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| `email` | required, email, exists:users,email |
| `token` | required, string |
| `password` | required, confirmed, StrongPassword |

**Success Response — `200`:**

```json
{
    "success": true,
    "message": "Password has been reset successfully."
}
```

**Error Responses:**

| Status | error_code |
|---|---|
| 400 | `INVALID_RESET_TOKEN` |
| 422 | `VALIDATION_ERROR` |

---

## Users Management

### 8. List Users

`GET /api/v1/users?search=&role=&is_active=&per_page=`

**Authentication:** Bearer Token + Active
**Authorization:** `users.view` (أو Super Admin)

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| `search` | string | بحث في name / email / username |
| `role` | string | super_admin / admin / distributor / customer_service |
| `is_active` | boolean | |
| `per_page` | int | عدد النتائج |

**Success Response — `200`:** (Collection)

```json
{
    "success": true,
    "data": [],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 0 },
    "links": {}
}
```

**Errors:** `401`, `403`, `403 FORBIDDEN`.

---

### 9. Create User

`POST /api/v1/users`

**Authentication:** Bearer Token + Active
**Authorization:** `users.create` (أو Super Admin)

**Request Body:**

```json
{
    "name": "Ahmed Ali",
    "username": "ahmed",
    "email": "ahmed@example.com",
    "password": "Password1!",
    "password_confirmation": "Password1!",
    "role": "distributor",
    "phone": "+9665...",
    "distributor_id": 1,
    "is_active": true
}
```

**Validation Rules:**

| Field | Rules |
|---|---|
| `name` | required, string, max:255 |
| `username` | nullable, string, max:50, unique |
| `email` | required, email, unique:users,email |
| `password` | required, confirmed, StrongPassword |
| `role` | required, in: [super_admin, admin, distributor, customer_service] |
| `phone` | nullable, string, max:20 |
| `is_active` | boolean |
| `distributor_id` | nullable, exists:distributors,id (إلزامي إذا role=distributor) |

**Business Rules:**
- إذا `role = distributor` يتطلب `distributor_id`.
- إذا `role != distributor` وممرر `distributor_id` → خطأ.
- Password يتم تخزينها Hash.
- `must_change_password` يُفعّل تلقائياً.

**Success Response — `201`:** (UserResource)

**Errors:** `401`, `403`, `422`.

---

### 10. Show User

`GET /api/v1/users/{user}`

**Authentication:** Bearer Token + Active
**Authorization:** `users.view` أو المستخدم نفسه.

---

### 11. Update User

`PUT /api/v1/users/{user}`

**Authentication:** Bearer Token + Active
**Authorization:** `users.update` (لا يمكن تعديل Super Admin إلا لـ Super Admin)

**Request Body:** نفس حدود Validation مع `sometimes`.

---

### 12. Delete User

`DELETE /api/v1/users/{user}`

**Authentication:** Bearer Token + Active
**Authorization:** `users.delete` (لا يمكن حذف نفسه ولا Super Admin لغير Super Admin)

**Success Response — `204`:**

```json
{
    "success": true,
    "message": "User deleted successfully."
}
```

**Errors:** `401`, `403`, `409 CANNOT_DELETE_SELF`.

---

### 13. Toggle User Status

`POST /api/v1/users/{user}/toggle-status`

**Authentication:** Bearer Token + Active
**Authorization:** `users.update`

- عند التعطيل → تُلغى جميع الـTokens الخاصة بالمستخدم.
- لا يتم حذف البيانات التاريخية.
- يُسجّل في Audit Log.

**Success Response — `200`:** (UserResource مع `is_active` المحدث)

---

## Localization

الصيغ التالية مترجمة في `lang/en` و `lang/ar`:

| Key | EN | AR |
|---|---|---|
| `auth_messages.logged_in` | Login successful. | تم تسجيل الدخول بنجاح. |
| `auth_messages.logged_out` | Logged out successfully. | تم تسجيل الخروج بنجاح. |
| `auth_messages.password_updated` | Password updated successfully. | تم تحديث كلمة المرور بنجاح. |
| `auth_messages.reset_link_sent` | If the email exists... | إذا كان البريد الإلكتروني موجوداً... |
| `errors.auth.invalid_credentials` | Invalid credentials. | بيانات الدخول غير صحيحة. |
| `errors.auth.account_deactivated` | Your account has been deactivated. | تم تعطيل حسابك. |
| `errors.auth.too_many_attempts` | Too many failed login attempts... | محاولات دخول فاشلة كثيرة... |

> الـ`error_code` ثابت (غير مترجم) ويعتمد عليه الـFrontend في الـLogic.
