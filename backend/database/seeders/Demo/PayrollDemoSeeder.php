<?php

namespace Database\Seeders\Demo;

use App\Actions\Payroll\ApprovePayrollRunAction;
use App\Actions\Payroll\CalculatePayrollRunAction;
use App\Actions\Payroll\CreatePayrollRunAction;
use App\Actions\Payroll\FinalizePayrollRunAction;
use App\Actions\Payroll\MarkPayrollPaidAction;
use App\Actions\Payroll\RecordEmployeeSalaryAction;
use App\Actions\Payroll\SaveSalaryStructureAction;
use App\Actions\Payroll\SaveTaxTableAction;
use App\Models\Company;
use App\Models\HR\Employee;
use App\Models\Payroll\PayComponent;
use App\Models\Payroll\PayrollRun;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEVELOPMENT ONLY. Pay components, a clearly non-statutory demo tax table, a
 * salary structure, a salary for every employee, two paid payroll runs for the
 * previous full months and the current month left Calculated (awaiting the
 * owner's approval). Runs are created by hr@ and approved by owner@.
 */
class PayrollDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'payroll');

        $components = $this->components();
        $structure = app(SaveSalaryStructureAction::class)->handle([
            'name' => 'Standard Monthly (Demo)',
            'description' => 'Base salary + housing (10%) + transport; provident fund 5% employee / 5% employer.',
            'currency' => $company->currency,
            'is_active' => true,
            'components' => array_map(fn (PayComponent $c) => ['pay_component_id' => $c->id], $components),
        ]);

        $this->taxTable();
        $this->salaries($structure->id);
        $this->runs();
    }

    /** @return list<PayComponent> */
    private function components(): array
    {
        $rows = [
            ['code' => 'HOUSING', 'name' => 'Housing Allowance', 'kind' => 'earning', 'calculation' => 'percent_of_base', 'default_rate' => '10', 'is_taxable' => true, 'affects_gross' => true, 'sort_order' => 10],
            ['code' => 'TRANSPORT', 'name' => 'Transport Allowance', 'kind' => 'earning', 'calculation' => 'fixed', 'default_amount' => $this->amount(150), 'is_taxable' => true, 'affects_gross' => true, 'sort_order' => 20],
            ['code' => 'PF', 'name' => 'Provident Fund (Employee)', 'kind' => 'deduction', 'calculation' => 'percent_of_base', 'default_rate' => '5', 'is_taxable' => false, 'reduces_taxable' => true, 'affects_gross' => false, 'sort_order' => 30],
            ['code' => 'PF_ER', 'name' => 'Provident Fund (Employer)', 'kind' => 'employer_contribution', 'calculation' => 'percent_of_base', 'default_rate' => '5', 'is_taxable' => false, 'affects_gross' => false, 'sort_order' => 40],
        ];

        return array_map(fn (array $row) => PayComponent::query()->create($row + [
            'description' => 'Demo component.',
            'is_active' => true,
        ]), $rows);
    }

    private function taxTable(): void
    {
        $s = $this->scale();

        app(SaveTaxTableAction::class)->handle([
            'name' => 'Demo tax table — not statutory',
            'effective_from' => $this->fiscalStart()->toDateString(),
            'period' => 'monthly',
            'is_active' => true,
            'notes' => 'Fictional progressive brackets for demonstration only. Not real tax law.',
            'brackets' => [
                ['from_amount' => 0, 'to_amount' => 1000 * $s, 'rate' => 0],
                ['from_amount' => 1000 * $s, 'to_amount' => 3000 * $s, 'rate' => 10],
                ['from_amount' => 3000 * $s, 'to_amount' => 6000 * $s, 'rate' => 20],
                ['from_amount' => 6000 * $s, 'to_amount' => null, 'rate' => 30],
            ],
        ]);
    }

    private function salaries(int $structureId): void
    {
        $record = app(RecordEmployeeSalaryAction::class);
        $hr = $this->demoUser('hr');
        $fiscalStart = $this->fiscalStart();

        foreach (Employee::query()->employed()->orderBy('id')->get() as $employee) {
            $from = $employee->hired_at === null ? $fiscalStart : Carbon::instance($employee->hired_at)->max($fiscalStart);

            $record->handle($employee, [
                'salary_structure_id' => $structureId,
                'base_salary' => (string) $employee->base_salary,
                'currency' => $this->company->currency,
                'effective_from' => $from->toDateString(),
                'notes' => 'Demo salary.',
            ], $hr->id);
        }
    }

    private function runs(): void
    {
        $create = app(CreatePayrollRunAction::class);
        $calculate = app(CalculatePayrollRunAction::class);
        $approve = app(ApprovePayrollRunAction::class);
        $finalize = app(FinalizePayrollRunAction::class);
        $paid = app(MarkPayrollPaidAction::class);
        $hr = $this->demoUser('hr');
        $owner = $this->demoUser('owner');
        $bank = $this->bankAccount();

        $months = [
            [$this->today()->subMonthsNoOverflow(2)->startOfMonth(), 'paid'],
            [$this->today()->subMonthNoOverflow()->startOfMonth(), 'paid'],
            [$this->today()->startOfMonth(), 'calculated'],
        ];

        foreach ($months as [$start, $target]) {
            if ($start->lessThan($this->fiscalStart())) {
                continue; // keep postings inside the open fiscal year
            }

            $end = $start->copy()->endOfMonth()->startOfDay();
            $createdAt = $target === 'paid' ? $end->copy()->subDays(5)->setTime(10, 0) : $this->realNow();

            /** @var PayrollRun $run */
            $run = $this->at($createdAt, fn () => $this->as('hr', fn () => $create->handle([
                'name' => 'Payroll '.$start->format('F Y'),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'payment_date' => $end->toDateString(),
                'notes' => 'Demo payroll run.',
            ], $hr->id)['run']));

            $this->at($createdAt, fn () => $this->as('hr', fn () => $calculate->handle($run, $hr->id)));

            if ($target !== 'paid') {
                continue;
            }

            $this->at($createdAt->copy()->addDays(2), fn () => $this->as('owner', fn () => $approve->handle($run, $owner->id)));
            $this->at($createdAt->copy()->addDays(3), fn () => $this->as('hr', fn () => $finalize->handle($run, $hr->id)));
            $this->at($end->copy()->setTime(15, 0), fn () => $this->as('hr', fn () => $paid->handle($run, [
                'bank_account_id' => $bank->id,
                'paid_at' => $end->toDateString(),
            ], $hr->id)));
        }

        $this->info(PayrollRun::query()->count().' payroll runs');
    }
}
