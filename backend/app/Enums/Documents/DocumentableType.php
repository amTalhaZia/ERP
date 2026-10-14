<?php

namespace App\Enums\Documents;

/**
 * Records a document may be linked to. Stored as a plain string (no FK) and
 * validated against the backing table, which may belong to another module.
 */
enum DocumentableType: string
{
    case Project = 'project';
    case Task = 'task';
    case Employee = 'employee';
    case Invoice = 'invoice';
    case Bill = 'bill';
    case PurchaseOrder = 'purchase_order';
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function table(): string
    {
        return match ($this) {
            self::Project => 'projects',
            self::Task => 'tasks',
            self::Employee => 'employees',
            self::Invoice => 'invoices',
            self::Bill => 'bills',
            self::PurchaseOrder => 'purchase_orders',
            self::Customer => 'customers',
            self::Supplier => 'suppliers',
        };
    }
}
