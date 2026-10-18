<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    | Used for CORS, password-reset links and Sanctum stateful domains.
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // Shared with the frontend proxy; only requests carrying it may report the visitor's
    // country/city (X-ERP-Geo-* headers) for the audit log. Empty = location not recorded.
    'geo_header_secret' => env('GEO_HEADER_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Tenant context
    |--------------------------------------------------------------------------
    */
    'company_header' => 'X-Company-Id',

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Minutes a password confirmation stays valid for sensitive actions.
        'password_confirmation_ttl' => (int) env('ERP_PASSWORD_CONFIRM_TTL', 15),
        // Minutes an un-finished 2FA challenge stays valid.
        'two_factor_challenge_ttl' => 5,
        'password_min_length' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bootstrap
    |--------------------------------------------------------------------------
    | Creating the very first administrator in production requires this flag so
    | it cannot happen by accident. Development environments never need it.
    */
    'allow_admin_bootstrap' => (bool) env('ERP_ALLOW_ADMIN_BOOTSTRAP', false),

    /*
    |--------------------------------------------------------------------------
    | Data retention (storage control)
    |--------------------------------------------------------------------------
    | Days to keep before `erp:prune` removes rows. NULL disables pruning.
    | Financial postings are never pruned.
    */
    'retention_days' => [
        'audit_logs' => (int) env('ERP_RETAIN_AUDIT_DAYS', 730),
        'notifications_read' => (int) env('ERP_RETAIN_NOTIFICATIONS_DAYS', 90),
        'login_activity' => (int) env('ERP_RETAIN_LOGIN_DAYS', 180),
        'export_files' => (int) env('ERP_RETAIN_EXPORTS_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports & exports
    |--------------------------------------------------------------------------
    | Exports up to sync_row_limit rows stream immediately; larger ones are
    | queued and the user is notified when the file is ready.
    */
    'reports' => [
        'sync_row_limit' => (int) env('ERP_EXPORT_SYNC_ROWS', 2000),
        'pdf_row_limit' => 5000,
    ],

    'version' => env('APP_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default' => 25,
        'max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'disk' => env('ERP_UPLOAD_DISK', 'local'),
        'max_kb' => (int) env('ERP_UPLOAD_MAX_KB', 10240),
        'image_max_kb' => 2048,
        'allowed_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Company role templates
    |--------------------------------------------------------------------------
    | Every new company receives these roles. Permission lists reference slugs
    | from PermissionSeeder. '*' grants every non-platform permission.
    */
    'role_templates' => [
        'company.owner' => [
            'name' => 'Company Owner',
            'description' => 'Full access to everything in the company.',
            'permissions' => ['*'],
        ],
        'company.admin' => [
            'name' => 'Company Administrator',
            'description' => 'Manages users, settings and all modules except payroll processing.',
            'permissions' => ['*', '!payroll.process'],
        ],
        'hr.manager' => [
            'name' => 'HR Manager',
            'description' => 'Employees, attendance, leave, performance and payroll.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view', 'departments.manage',
                'employees.*', 'attendance.*', 'leave.*', 'shifts.manage', 'performance.*',
                'payroll.view', 'payroll.manage', 'payroll.process', 'payslips.view.own',
                'documents.view', 'documents.manage', 'approvals.view', 'reports.view', 'reports.export',
            ],
        ],
        'accountant' => [
            'name' => 'Accountant',
            'description' => 'Finance, accounting, invoices, bills, payments and financial reports.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view',
                'accounts.*', 'journals.*', 'invoices.*', 'bills.*', 'payments.*', 'expenses.view', 'expenses.approve',
                'bank.manage', 'tax.manage', 'periods.manage', 'reports.financial.view',
                'customers.view', 'suppliers.view', 'payroll.view',
                'approvals.view', 'reports.view', 'reports.export', 'documents.view',
            ],
        ],
        'sales.manager' => [
            'name' => 'Sales Manager',
            'description' => 'Customers, leads, quotations, sales orders and invoices.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view',
                'customers.*', 'leads.*', 'quotations.*', 'sales_orders.*',
                'invoices.view', 'invoices.create', 'invoices.update', 'payments.view',
                'products.view', 'inventory.view', 'approvals.view', 'reports.view', 'reports.export', 'documents.view',
            ],
        ],
        'inventory.manager' => [
            'name' => 'Inventory Manager',
            'description' => 'Products, warehouses and all stock operations.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view',
                'products.*', 'warehouses.*', 'inventory.*', 'goods_receipts.manage',
                'purchase_orders.view', 'sales_orders.view', 'suppliers.view',
                'approvals.view', 'reports.view', 'reports.export', 'documents.view',
            ],
        ],
        'procurement.officer' => [
            'name' => 'Procurement Officer',
            'description' => 'Suppliers, requisitions, purchase orders and goods receipts.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view',
                'suppliers.*', 'requisitions.*', 'purchase_orders.view', 'purchase_orders.manage',
                'goods_receipts.manage', 'bills.view', 'bills.create', 'products.view', 'inventory.view',
                'approvals.view', 'reports.view', 'documents.view',
            ],
        ],
        'department.manager' => [
            'name' => 'Department Manager',
            'description' => 'Approves leave, requisitions and expenses for their team; manages projects.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view',
                'employees.view', 'attendance.view', 'leave.view', 'leave.request', 'leave.approve',
                'requisitions.view', 'requisitions.create', 'requisitions.approve',
                'expenses.view', 'expenses.submit', 'expenses.approve',
                'projects.*', 'tasks.*', 'time_entries.manage', 'approvals.view', 'reports.view', 'documents.view',
                'payslips.view.own',
            ],
        ],
        'employee' => [
            'name' => 'Employee',
            'description' => 'Self-service: own leave, expenses, tasks, time and payslips.',
            'permissions' => [
                'dashboard.view', 'company.view', 'leave.request', 'expenses.submit',
                'tasks.view', 'tasks.manage', 'time_entries.manage', 'projects.view', 'payslips.view.own', 'documents.view',
            ],
        ],
        'auditor' => [
            'name' => 'Read-Only Auditor',
            'description' => 'Reads every module including audit logs; cannot change anything.',
            'permissions' => ['*.view', 'reports.financial.view', 'audit.view', 'reports.export'],
        ],
    ],

];
