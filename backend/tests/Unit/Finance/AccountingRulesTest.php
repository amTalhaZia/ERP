<?php

namespace Tests\Unit\Finance;

use App\Enums\Finance\AccountSubtype;
use App\Enums\Finance\AccountType;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\ReportService;
use App\Support\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountingRulesTest extends TestCase
{
    public function test_natural_balance_side_per_account_type(): void
    {
        $this->assertTrue(AccountType::Asset->isDebitNormal());
        $this->assertTrue(AccountType::Expense->isDebitNormal());
        $this->assertFalse(AccountType::Liability->isDebitNormal());
        $this->assertFalse(AccountType::Equity->isDebitNormal());
        $this->assertFalse(AccountType::Revenue->isDebitNormal());
    }

    /** @return array<string, array{AccountType, ?string, string}> */
    public static function cashFlowSections(): array
    {
        return [
            'revenue is operating' => [AccountType::Revenue, 'sales_revenue', 'operating'],
            'payables are operating' => [AccountType::Liability, 'payable', 'operating'],
            'fixed assets are investing' => [AccountType::Asset, 'fixed_asset', 'investing'],
            'loans are financing' => [AccountType::Liability, 'loan', 'financing'],
            'equity is financing' => [AccountType::Equity, 'share_capital', 'financing'],
            'unknown subtype falls back to operating' => [AccountType::Expense, null, 'operating'],
        ];
    }

    #[DataProvider('cashFlowSections')]
    public function test_cash_flow_classification(AccountType $type, ?string $subtype, string $expected): void
    {
        $this->assertSame($expected, AccountSubtype::cashFlowSection($type, $subtype));
    }

    public function test_previous_range_has_equal_length(): void
    {
        $this->assertSame(['from' => '2025-12-01', 'to' => '2025-12-31'], ReportService::previousRange('2026-01-01', '2026-01-31'));
        $this->assertSame(['from' => '2025-01-01', 'to' => '2025-12-31'], ReportService::previousRange('2026-01-01', '2026-12-31'));
    }

    public function test_template_codes_are_unique_and_balanced_types_present(): void
    {
        $codes = array_column(ChartOfAccountsTemplate::accounts(), 'code');

        $this->assertSame($codes, array_unique($codes));
        $this->assertGreaterThanOrEqual(33, count($codes));
        foreach (['1100', '1200', '2000', '2100', '4000', '5000'] as $required) {
            $this->assertContains($required, $codes);
        }
    }

    public function test_many_small_postings_sum_exactly(): void
    {
        $this->assertTrue(Money::equals(Money::sum(array_fill(0, 1000, '0.0001')), '0.1'));
        $this->assertSame('0.0000', Money::sub(Money::sum(['33.3333', '33.3333', '33.3334']), '100'));
    }
}
