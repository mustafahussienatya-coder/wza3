<?php

return [
    'general' => [
        'forbidden' => [
            'error_code' => 'FORBIDDEN',
            'message' => 'غير مصرح لك بتنفيذ هذا الإجراء.',
            'http_status' => 403,
        ],
    ],
    'auth' => [
        'invalid_credentials' => [
            'error_code' => 'INVALID_CREDENTIALS',
            'message' => 'بيانات الدخول غير صحيحة.',
            'http_status' => 401,
        ],
        'account_deactivated' => [
            'error_code' => 'ACCOUNT_DEACTIVATED',
            'message' => 'تم تعطيل حسابك.',
            'http_status' => 403,
        ],
        'too_many_attempts' => [
            'error_code' => 'TOO_MANY_LOGIN_ATTEMPTS',
            'message' => 'محاولات دخول فاشلة كثيرة. تم قفل الحساب مؤقتاً.',
            'http_status' => 429,
        ],
        'current_password_incorrect' => [
            'error_code' => 'CURRENT_PASSWORD_INCORRECT',
            'message' => 'كلمة المرور الحالية غير صحيحة.',
            'http_status' => 422,
        ],
        'invalid_reset_token' => [
            'error_code' => 'INVALID_RESET_TOKEN',
            'message' => 'رمز إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية.',
            'http_status' => 400,
        ],
        'required_temporary_password_change' => [
            'error_code' => 'TEMPORARY_PASSWORD_REQUIRED',
            'message' => 'يجب عليك تغيير كلمة المرور المؤقتة قبل المتابعة.',
            'http_status' => 403,
        ],
    ],
    'user' => [
        'not_allowed_to_create_super_admin' => [
            'error_code' => 'SUPER_ADMIN_CREATION_FORBIDDEN',
            'message' => 'غير مسموح لك بإنشاء مدير عام.',
            'http_status' => 403,
        ],
        'cannot_delete_self' => [
            'error_code' => 'CANNOT_DELETE_SELF',
            'message' => 'لا يمكنك حذف حسابك الخاص.',
            'http_status' => 409,
        ],
    ],
    'category' => [
        'cannot_delete' => [
            'error_code' => 'CATEGORY_CANNOT_DELETE',
            'message' => 'لا يمكن حذف هذه الفئة.',
            'http_status' => 409,
        ],
        'has_children' => [
            'error_code' => 'CATEGORY_HAS_CHILDREN',
            'message' => 'تحتوي هذه الفئة على فئات فرعية ولا يمكن حذفها.',
            'http_status' => 409,
        ],
    ],
    'area' => [
        'in_use' => [
            'error_code' => 'AREA_IN_USE',
            'message' => 'لا يمكن حذف المنطقة لأنها مرتبطة بموزعين.',
            'http_status' => 409,
        ],
    ],
    'inventory' => [
        'insufficient_stock' => [
            'error_code' => 'INSUFFICIENT_STOCK',
            'message' => 'الرصيد غير كافٍ لهذه العملية (منتج: :product، المتاح: :available :unit، المطلوب: :required :unit).',
            'http_status' => 409,
        ],
        'cannot_correct_movement' => [
            'error_code' => 'CANNOT_CORRECT_MOVEMENT',
            'message' => 'لا يمكن تصحيح هذه الحركة لأن دفعة الإدخال استُهلكت بالكامل.',
            'http_status' => 422,
        ],
        'invalid_stock_operation' => [
            'error_code' => 'INVALID_STOCK_OPERATION',
            'message' => 'المنتج أو المخزن أو الوحدة غير صالحين لهذه العملية.',
            'http_status' => 422,
        ],
    ],
    'custody' => [
        'insufficient_warehouse_stock' => [
            'error_code' => 'INSUFFICIENT_WAREHOUSE_STOCK',
            'message' => 'رصيد المخزن غير كافٍ لإتمام هذا التسليم.',
            'http_status' => 409,
        ],
        'insufficient_distributor_stock' => [
            'error_code' => 'INSUFFICIENT_DISTRIBUTOR_STOCK',
            'message' => 'رصيد عهدة الموزع غير كافٍ لهذه العملية (منتج: :product، المتاح: :available :unit، المطلوب: :required :unit).',
            'http_status' => 409,
        ],
        'invalid_status_transition' => [
            'error_code' => 'INVALID_ISSUE_STATUS_TRANSITION',
            'message' => 'هذا الإجراء غير مسموح به لحالة الإيصال الحالية.',
            'http_status' => 422,
        ],
        'invalid_custody_operation' => [
            'error_code' => 'INVALID_CUSTODY_OPERATION',
            'message' => 'الموزع أو المخزن أو المنتج أو الوحدة غير صالحين لهذه العملية.',
            'http_status' => 422,
        ],
        'empty_correction' => [
            'error_code' => 'EMPTY_CORRECTION',
            'message' => 'لم يتم العثور على تغييرات في بيانات التصحيح.',
            'http_status' => 422,
        ],
    ],
    'distributor' => [
        'not_linked' => [
            'error_code' => 'DISTRIBUTOR_NOT_LINKED',
            'message' => 'حسابك غير مرتبط بسجل موزع.',
            'http_status' => 403,
        ],
        'account_suspended' => [
            'error_code' => 'DISTRIBUTOR_SUSPENDED',
            'message' => 'تم إيقاف حساب الموزع الخاص بك.',
            'http_status' => 403,
        ],
    ],
    'invoice' => [
        'invalid_status_transition' => [
            'error_code' => 'INVALID_INVOICE_STATUS_TRANSITION',
            'message' => 'هذا الإجراء غير مسموح به لحالة الفاتورة الحالية.',
            'http_status' => 422,
        ],
        'cannot_edit_confirmed_invoice' => [
            'error_code' => 'CANNOT_EDIT_CONFIRMED_INVOICE',
            'message' => 'لا يمكن تعديل فاتورة تم تحصيلها.',
            'http_status' => 422,
        ],
        'customer_not_owned' => [
            'error_code' => 'CUSTOMER_NOT_OWNED',
            'message' => 'هذا العميل ليس تابعاً لك.',
            'http_status' => 403,
        ],
        'customer_suspended' => [
            'error_code' => 'CUSTOMER_SUSPENDED',
            'message' => 'لا يمكن إتمام الفاتورة لأن العميل غير نشط.',
            'http_status' => 409,
        ],
        'invalid_item' => [
            'error_code' => 'INVALID_INVOICE_ITEM',
            'message' => 'المنتج أو الوحدة غير صالحين لهذه الفاتورة.',
            'http_status' => 422,
        ],
        'invalid_quantity' => [
            'error_code' => 'INVALID_INVOICE_QUANTITY',
            'message' => 'الكمية يجب أن تكون أكبر من صفر.',
            'http_status' => 422,
        ],
        'insufficient_stock' => [
            'error_code' => 'INSUFFICIENT_STOCK',
            'message' => 'رصيد المخزن غير كافٍ لإتمام هذه الفاتورة (منتج: :product، المتاح: :available :unit، المطلوب: :required :unit).',
            'http_status' => 409,
        ],
    ],
    'collection' => [
        'exceeds_outstanding' => [
            'error_code' => 'PAYMENT_EXCEEDS_OUTSTANDING',
            'message' => 'المبلغ المدفوع أكبر من الرصيد المستحق للعميل.',
            'http_status' => 409,
        ],
        'invalid_operation' => [
            'error_code' => 'INVALID_COLLECTION_OPERATION',
            'message' => 'بيانات التحصيل غير صالحة.',
            'http_status' => 422,
        ],
        'customer_not_owned' => [
            'error_code' => 'CUSTOMER_NOT_OWNED',
            'message' => 'هذا العميل ليس تابعاً لك.',
            'http_status' => 403,
        ],
    ],
    'settlement' => [
        'exceeds_collected' => [
            'error_code' => 'SETTLEMENT_EXCEEDS_COLLECTED',
            'message' => 'مبلغ التسليم أكبر من النقد المحصّل غير المسلَّم.',
            'http_status' => 409,
        ],
        'invalid_operation' => [
            'error_code' => 'INVALID_SETTLEMENT_OPERATION',
            'message' => 'بيانات التسليم غير صالحة.',
            'http_status' => 422,
        ],
    ],
];
