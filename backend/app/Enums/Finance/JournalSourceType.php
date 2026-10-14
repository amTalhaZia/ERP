<?php

namespace App\Enums\Finance;

/**
 * Well-known `journal_entries.source_type` values. Sub-ledgers may pass any
 * snake_case string (≤30 chars) to LedgerPoster; these are the ones the UI knows.
 */
enum JournalSourceType: string
{
    case Manual = 'manual';
    case Invoice = 'invoice';
    case Bill = 'bill';
    case Payment = 'payment';
    case ExpenseClaim = 'expense_claim';
    case PayrollRun = 'payroll_run';
    case StockAdjustment = 'stock_adjustment';
    case OpeningBalance = 'opening_balance';
}
