<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Global permission catalogue. Idempotent — safe to re-run on every deploy.
 * Mirror: frontend/src/lib/auth/permissions.ts
 */
class PermissionSeeder extends Seeder
{
    /**
     * @return array<string, array<string, string>> module => [slug => description]
     */
    public static function catalogue(): array
    {
        return [
            'dashboard' => ['dashboard.view' => 'View executive dashboard'],
            'company' => [
                'company.view' => 'View company profile',
                'company.settings.manage' => 'Manage company settings & branding',
                'branches.view' => 'View branches',
                'branches.manage' => 'Create/update/delete branches',
                'departments.view' => 'View departments',
                'departments.manage' => 'Manage departments',
            ],
            'access' => [
                'users.view' => 'View users',
                'users.manage' => 'Invite, update, deactivate users',
                'roles.view' => 'View roles & permissions',
                'roles.manage' => 'Manage roles & permissions',
            ],
            'hr' => [
                'employees.view' => 'View employees',
                'employees.create' => 'Create employees',
                'employees.update' => 'Update employees',
                'employees.delete' => 'Delete employees',
                'employees.documents.view' => 'View employee documents',
                'attendance.view' => 'View attendance',
                'attendance.manage' => 'Record/edit attendance',
                'leave.view' => 'View leave requests',
                'leave.request' => 'Submit own leave requests',
                'leave.approve' => 'Approve/reject leave',
                'leave.manage' => 'Manage leave types & balances',
                'shifts.manage' => 'Manage shifts & holidays',
                'performance.view' => 'View performance reviews',
                'performance.manage' => 'Manage performance reviews',
            ],
            'payroll' => [
                'payroll.view' => 'View payroll',
                'payroll.manage' => 'Manage salary structures',
                'payroll.process' => 'Run & approve payroll',
                'payslips.view.own' => 'View own payslips',
            ],
            'finance' => [
                'accounts.view' => 'View chart of accounts',
                'accounts.manage' => 'Manage chart of accounts',
                'journals.view' => 'View journal entries',
                'journals.create' => 'Create journal entries',
                'journals.post' => 'Post journal entries',
                'invoices.view' => 'View invoices',
                'invoices.create' => 'Create invoices',
                'invoices.update' => 'Update draft invoices',
                'invoices.approve' => 'Approve invoices',
                'invoices.delete' => 'Delete draft invoices',
                'bills.view' => 'View supplier bills',
                'bills.create' => 'Create supplier bills',
                'bills.approve' => 'Approve supplier bills',
                'payments.view' => 'View payments & receipts',
                'payments.create' => 'Record payments & receipts',
                'expenses.view' => 'View expense claims',
                'expenses.submit' => 'Submit expense claims',
                'expenses.approve' => 'Approve expense claims',
                'bank.manage' => 'Manage cash & bank accounts',
                'tax.manage' => 'Manage tax configuration',
                'periods.manage' => 'Manage financial periods',
                'reports.financial.view' => 'View financial reports',
            ],
            'sales' => [
                'customers.view' => 'View customers',
                'customers.manage' => 'Manage customers',
                'leads.view' => 'View leads & opportunities',
                'leads.manage' => 'Manage leads & opportunities',
                'quotations.view' => 'View quotations',
                'quotations.manage' => 'Manage quotations',
                'sales_orders.view' => 'View sales orders',
                'sales_orders.manage' => 'Manage sales orders',
                'sales_orders.approve' => 'Approve sales orders',
            ],
            'procurement' => [
                'suppliers.view' => 'View suppliers',
                'suppliers.manage' => 'Manage suppliers',
                'requisitions.view' => 'View purchase requisitions',
                'requisitions.create' => 'Create purchase requisitions',
                'requisitions.approve' => 'Approve purchase requisitions',
                'purchase_orders.view' => 'View purchase orders',
                'purchase_orders.manage' => 'Manage purchase orders',
                'purchase_orders.approve' => 'Approve purchase orders',
                'goods_receipts.manage' => 'Record goods received',
            ],
            'inventory' => [
                'products.view' => 'View products',
                'products.manage' => 'Manage products & categories',
                'warehouses.view' => 'View warehouses',
                'warehouses.manage' => 'Manage warehouses & locations',
                'inventory.view' => 'View stock levels & movements',
                'inventory.receive' => 'Receive stock',
                'inventory.issue' => 'Issue stock',
                'inventory.transfer' => 'Transfer stock',
                'inventory.adjust' => 'Adjust stock',
            ],
            'projects' => [
                'projects.view' => 'View projects',
                'projects.manage' => 'Manage projects',
                'tasks.view' => 'View tasks',
                'tasks.manage' => 'Manage tasks',
                'time_entries.manage' => 'Log time',
            ],
            'documents' => [
                'documents.view' => 'View documents',
                'documents.manage' => 'Upload/manage documents',
            ],
            'system' => [
                'approvals.view' => 'View approval inbox',
                'reports.view' => 'View reports',
                'reports.export' => 'Export reports',
                'audit.view' => 'View audit logs',
            ],
            'platform' => [
                'platform.companies.manage' => 'Manage all companies',
                'platform.users.manage' => 'Manage platform users',
                'platform.system.view' => 'View system health & database overview',
            ],
        ];
    }

    public function run(): void
    {
        $rows = [];
        $now = now();

        foreach (self::catalogue() as $module => $permissions) {
            foreach ($permissions as $slug => $description) {
                $rows[] = [
                    'slug' => $slug,
                    'module' => $module,
                    'description' => $description,
                    'is_platform' => $module === 'platform',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        Permission::query()->upsert($rows, ['slug'], ['module', 'description', 'is_platform', 'updated_at']);
    }
}
