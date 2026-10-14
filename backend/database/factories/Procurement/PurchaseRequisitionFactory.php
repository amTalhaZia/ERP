<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\RequisitionPriority;
use App\Enums\Procurement\RequisitionStatus;
use App\Models\Company;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\PurchaseRequisitionLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequisition>
 */
class PurchaseRequisitionFactory extends Factory
{
    protected $model = PurchaseRequisition::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'PR-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'requester_id' => User::factory(),
            'needed_by' => now()->addDays(14)->toDateString(),
            'priority' => RequisitionPriority::Normal,
            'status' => RequisitionStatus::Draft,
            'justification' => fake()->sentence(),
            'estimated_total' => '0.0000',
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => RequisitionStatus::Submitted, 'submitted_at' => now()]);
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn () => [
            'status' => RequisitionStatus::Approved,
            'submitted_at' => now()->subHours(4),
            'approver_id' => $approver?->id ?? User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Budget exceeded'): static
    {
        return $this->state(fn () => [
            'status' => RequisitionStatus::Rejected,
            'submitted_at' => now()->subHours(4),
            'approver_id' => User::factory(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /** Attach $count lines (same company) after creating. */
    public function withLines(int $count = 2): static
    {
        return $this->afterCreating(function (PurchaseRequisition $requisition) use ($count): void {
            PurchaseRequisitionLine::factory()->count($count)->sequence(fn ($sequence) => ['line_no' => $sequence->index + 1])
                ->create(['company_id' => $requisition->company_id, 'purchase_requisition_id' => $requisition->id]);

            $requisition->forceFill(['estimated_total' => $requisition->lines()->sum('line_total')])->save();
        });
    }
}
