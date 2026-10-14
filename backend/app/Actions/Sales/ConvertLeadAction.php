<?php

namespace App\Actions\Sales;

use App\Enums\Sales\CustomerType;
use App\Enums\Sales\LeadStatus;
use App\Models\Sales\Customer;
use App\Models\Sales\Lead;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Converts a lead into a customer (or links an existing customer with the
 * same email), optionally opens an opportunity, and marks the lead Converted.
 */
class ConvertLeadAction
{
    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return array{customer: Customer, opportunity: Opportunity|null, customer_created: bool}
     */
    public function handle(Lead $lead, bool $createOpportunity = false, ?int $stageId = null, ?User $actor = null): array
    {
        return DB::transaction(function () use ($lead, $createOpportunity, $stageId, $actor): array {
            $lead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($lead->status === LeadStatus::Converted) {
                throw new ConflictHttpException('This lead has already been converted.');
            }

            $customer = $lead->email
                ? Customer::query()->where('email', $lead->email)->first()
                : null;
            $customerCreated = false;

            if ($customer === null) {
                $customer = Customer::query()->create([
                    'code' => $this->numberer->next('customer', yearly: false, prefix: 'CUST', padding: 5),
                    'name' => $lead->company_name ?: $lead->name,
                    'type' => $lead->company_name ? CustomerType::Company : CustomerType::Individual,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'currency' => $this->tenant->company()?->currency ?? 'USD',
                    'sales_rep_id' => $lead->assigned_to,
                    'source' => $lead->source?->value,
                    'notes' => $lead->notes,
                    'is_active' => true,
                ]);
                $customerCreated = true;
            }

            $opportunity = null;

            if ($createOpportunity) {
                $stage = $stageId !== null
                    ? PipelineStage::query()->findOrFail($stageId)
                    : PipelineStage::query()->where('is_won', false)->where('is_lost', false)->orderBy('position')->first();

                if ($stage === null) {
                    throw new ConflictHttpException('No pipeline stage is available to open an opportunity.');
                }

                $opportunity = new Opportunity([
                    'title' => $lead->company_name ? "{$lead->company_name} — {$lead->name}" : $lead->name,
                    'customer_id' => $customer->id,
                    'lead_id' => $lead->id,
                    'value' => (string) ($lead->estimated_value ?? '0'),
                    'currency' => $customer->currency,
                    'expected_close_date' => null,
                    'owner_id' => $lead->assigned_to ?? $actor?->id,
                ]);
                $opportunity->applyStage($stage);
                $opportunity->save();
            }

            $lead->forceFill([
                'status' => LeadStatus::Converted,
                'converted_customer_id' => $customer->id,
                'converted_at' => now(),
            ])->save();

            $this->audit->log('leads.converted', $lead, context: [
                'customer_id' => $customer->id,
                'customer_created' => $customerCreated,
                'opportunity_id' => $opportunity?->id,
            ]);

            return ['customer' => $customer, 'opportunity' => $opportunity, 'customer_created' => $customerCreated];
        });
    }
}
