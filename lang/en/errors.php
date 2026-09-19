<?php

return [
    'general' => [
        'forbidden' => [
            'error_code' => 'FORBIDDEN',
            'message' => 'You are not authorized to perform this action.',
            'http_status' => 403,
        ],
    ],
    'auth' => [
        'invalid_credentials' => [
            'error_code' => 'INVALID_CREDENTIALS',
            'message' => 'Invalid credentials.',
            'http_status' => 401,
        ],
        'account_deactivated' => [
            'error_code' => 'ACCOUNT_DEACTIVATED',
            'message' => 'Your account has been deactivated.',
            'http_status' => 403,
        ],
        'too_many_attempts' => [
            'error_code' => 'TOO_MANY_LOGIN_ATTEMPTS',
            'message' => 'Too many failed login attempts. Account is temporarily locked.',
            'http_status' => 429,
        ],
        'current_password_incorrect' => [
            'error_code' => 'CURRENT_PASSWORD_INCORRECT',
            'message' => 'Current password is incorrect.',
            'http_status' => 422,
        ],
        'invalid_reset_token' => [
            'error_code' => 'INVALID_RESET_TOKEN',
            'message' => 'This password reset token is invalid or expired.',
            'http_status' => 400,
        ],
        'required_temporary_password_change' => [
            'error_code' => 'TEMPORARY_PASSWORD_REQUIRED',
            'message' => 'You must change your temporary password before continuing.',
            'http_status' => 403,
        ],
    ],
    'user' => [
        'not_allowed_to_create_super_admin' => [
            'error_code' => 'SUPER_ADMIN_CREATION_FORBIDDEN',
            'message' => 'You are not allowed to create a Super Admin.',
            'http_status' => 403,
        ],
        'cannot_delete_self' => [
            'error_code' => 'CANNOT_DELETE_SELF',
            'message' => 'You cannot delete your own account.',
            'http_status' => 409,
        ],
    ],
    'category' => [
        'cannot_delete' => [
            'error_code' => 'CATEGORY_CANNOT_DELETE',
            'message' => 'This category cannot be deleted.',
            'http_status' => 409,
        ],
        'has_children' => [
            'error_code' => 'CATEGORY_HAS_CHILDREN',
            'message' => 'This category has subcategories and cannot be deleted.',
            'http_status' => 409,
        ],
    ],
    'area' => [
        'in_use' => [
            'error_code' => 'AREA_IN_USE',
            'message' => 'Area cannot be deleted because it is assigned to distributors.',
            'http_status' => 409,
        ],
    ],
    'inventory' => [
        'insufficient_stock' => [
            'error_code' => 'INSUFFICIENT_STOCK',
            'message' => 'Insufficient stock for this operation (product: :product, available: :available :unit, required: :required :unit).',
            'http_status' => 409,
        ],
        'cannot_correct_movement' => [
            'error_code' => 'CANNOT_CORRECT_MOVEMENT',
            'message' => 'This movement cannot be corrected because its batch was fully consumed.',
            'http_status' => 422,
        ],
        'invalid_stock_operation' => [
            'error_code' => 'INVALID_STOCK_OPERATION',
            'message' => 'The product, warehouse or unit is not valid for this operation.',
            'http_status' => 422,
        ],
    ],
    'custody' => [
        'insufficient_warehouse_stock' => [
            'error_code' => 'INSUFFICIENT_WAREHOUSE_STOCK',
            'message' => 'Warehouse stock is not enough to complete this issue.',
            'http_status' => 409,
        ],
        'insufficient_distributor_stock' => [
            'error_code' => 'INSUFFICIENT_DISTRIBUTOR_STOCK',
            'message' => 'Distributor custody is not enough for this operation (product: :product, available: :available :unit, required: :required :unit).',
            'http_status' => 409,
        ],
        'invalid_status_transition' => [
            'error_code' => 'INVALID_ISSUE_STATUS_TRANSITION',
            'message' => 'This action is not allowed for the current issue status.',
            'http_status' => 422,
        ],
        'invalid_custody_operation' => [
            'error_code' => 'INVALID_CUSTODY_OPERATION',
            'message' => 'The distributor, warehouse, product or unit is not valid for this operation.',
            'http_status' => 422,
        ],
        'empty_correction' => [
            'error_code' => 'EMPTY_CORRECTION',
            'message' => 'No changes detected in the correction data.',
            'http_status' => 422,
        ],
    ],
    'distributor' => [
        'not_linked' => [
            'error_code' => 'DISTRIBUTOR_NOT_LINKED',
            'message' => 'Your account is not linked to a distributor record.',
            'http_status' => 403,
        ],
        'account_suspended' => [
            'error_code' => 'DISTRIBUTOR_SUSPENDED',
            'message' => 'Your distributor account has been suspended.',
            'http_status' => 403,
        ],
    ],
    'invoice' => [
        'invalid_status_transition' => [
            'error_code' => 'INVALID_INVOICE_STATUS_TRANSITION',
            'message' => 'This action is not allowed for the current invoice status.',
            'http_status' => 422,
        ],
        'cannot_edit_confirmed_invoice' => [
            'error_code' => 'CANNOT_EDIT_CONFIRMED_INVOICE',
            'message' => 'A confirmed invoice cannot be edited.',
            'http_status' => 422,
        ],
        'customer_not_owned' => [
            'error_code' => 'CUSTOMER_NOT_OWNED',
            'message' => 'This customer is not assigned to you.',
            'http_status' => 403,
        ],
        'customer_suspended' => [
            'error_code' => 'CUSTOMER_SUSPENDED',
            'message' => 'The invoice cannot be processed because the customer is inactive.',
            'http_status' => 409,
        ],
        'invalid_item' => [
            'error_code' => 'INVALID_INVOICE_ITEM',
            'message' => 'The product or unit is not valid for this invoice.',
            'http_status' => 422,
        ],
        'invalid_quantity' => [
            'error_code' => 'INVALID_INVOICE_QUANTITY',
            'message' => 'The quantity must be greater than zero.',
            'http_status' => 422,
        ],
        'insufficient_stock' => [
            'error_code' => 'INSUFFICIENT_STOCK',
            'message' => 'Warehouse stock is not enough to complete this invoice (product: :product, available: :available :unit, required: :required :unit).',
            'http_status' => 409,
        ],
    ],
    'collection' => [
        'exceeds_outstanding' => [
            'error_code' => 'PAYMENT_EXCEEDS_OUTSTANDING',
            'message' => 'The payment amount exceeds the customer outstanding balance.',
            'http_status' => 409,
        ],
        'invalid_operation' => [
            'error_code' => 'INVALID_COLLECTION_OPERATION',
            'message' => 'The collection data is invalid.',
            'http_status' => 422,
        ],
        'customer_not_owned' => [
            'error_code' => 'CUSTOMER_NOT_OWNED',
            'message' => 'This customer is not assigned to you.',
            'http_status' => 403,
        ],
    ],
    'settlement' => [
        'exceeds_collected' => [
            'error_code' => 'SETTLEMENT_EXCEEDS_COLLECTED',
            'message' => 'The settlement amount exceeds the collected cash not yet settled.',
            'http_status' => 409,
        ],
        'invalid_operation' => [
            'error_code' => 'INVALID_SETTLEMENT_OPERATION',
            'message' => 'The settlement data is invalid.',
            'http_status' => 422,
        ],
    ],
];
