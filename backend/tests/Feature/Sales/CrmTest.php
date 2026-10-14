<?php

namespace Tests\Feature\Sales;

use App\Events\CompanyProvisioned;
use App\Models\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Lead;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use App\Models\User;
use Tests\Feature\Sales\Concerns\ArrangesSales;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use ArrangesSales;

    /**
     * @return array{0: User, 1: Company, 2: array<string, PipelineStage>}
     */
    private function crmUser(array $permissions = ['leads.view', 'leads.manage', 'customers.view']): array
    {
        [$user, $company] = $this->userWith($permissions);
        $stages = [];

        foreach (PipelineStage::defaults() as $stage) {
            $stages[$stage['name']] = PipelineStage::factory()->for($company)->create($stage);
        }

        return [$user, $company, $stages];
    }

    public function test_pipeline_stages_are_seeded_when_a_company_is_provisioned(): void
    {
        $company = Company::factory()->create();

        CompanyProvisioned::dispatch($company);

        $stages = PipelineStage::forCompany($company)->orderBy('position')->get();
        $this->assertCount(6, $stages);
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'], $stages->pluck('name')->all());
        $this->assertSame([10, 30, 60, 80, 100, 0], $stages->pluck('probability')->all());
        $this->assertTrue($stages[4]->is_won);
        $this->assertTrue($stages[5]->is_lost);

        // Idempotent.
        CompanyProvisioned::dispatch($company);
        $this->assertSame(6, PipelineStage::forCompany($company)->count());
    }

    public function test_lead_conversion_creates_customer_opportunity_and_marks_lead(): void
    {
        [$user, $company, $stages] = $this->crmUser();
        $lead = Lead::factory()->for($company)->create([
            'name' => 'Jane Roe', 'company_name' => 'Roe Industries', 'email' => 'jane@roe.test', 'estimated_value' => '12000',
            'assigned_to' => $user->id,
        ]);

        $response = $this->actAs($user, $company)
            ->postJson("/api/v1/leads/{$lead->id}/convert", ['create_opportunity' => true, 'stage_id' => $stages['Qualified']->id]);

        $response->assertOk()
            ->assertJsonPath('data.customer_created', true)
            ->assertJsonPath('data.customer.name', 'Roe Industries')
            ->assertJsonPath('data.customer.code', 'CUST-00001')
            ->assertJsonPath('data.lead.status', 'converted')
            ->assertJsonPath('data.opportunity.value', '12000.0000')
            ->assertJsonPath('data.opportunity.probability', 30)
            ->assertJsonPath('data.opportunity.stage.id', $stages['Qualified']->id);

        $customerId = $response->json('data.customer.id');
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'company_id' => $company->id, 'email' => 'jane@roe.test', 'sales_rep_id' => $user->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'converted', 'converted_customer_id' => $customerId]);
        $this->assertDatabaseHas('opportunities', ['lead_id' => $lead->id, 'customer_id' => $customerId, 'status' => 'open']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leads.converted', 'entity_id' => $lead->id]);

        $this->postJson("/api/v1/leads/{$lead->id}/convert")->assertStatus(409);
    }

    public function test_lead_conversion_links_existing_customer_by_email(): void
    {
        [$user, $company] = $this->crmUser();
        $existing = Customer::factory()->for($company)->create(['email' => 'known@cust.test']);
        $lead = Lead::factory()->for($company)->create(['email' => 'known@cust.test']);

        $this->actAs($user, $company)->postJson("/api/v1/leads/{$lead->id}/convert")
            ->assertOk()
            ->assertJsonPath('data.customer_created', false)
            ->assertJsonPath('data.customer.id', $existing->id)
            ->assertJsonPath('data.opportunity', null);

        $this->assertSame(1, Customer::forCompany($company)->count());
    }

    public function test_leads_crud_validation_permissions_and_tenancy(): void
    {
        [$user, $company] = $this->crmUser();
        [$viewer] = $this->userWith(['leads.view'], $company);
        $foreign = Lead::factory()->create();
        $outsider = User::factory()->create();

        $this->actAs($user, $company);
        $this->postJson('/api/v1/leads', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $id = $this->postJson('/api/v1/leads', ['name' => 'Lead A', 'source' => 'cold_call', 'estimated_value' => '99.5'])
            ->assertCreated()->assertJsonPath('data.status', 'new')->assertJsonPath('data.estimated_value', '99.5000')->json('data.id');

        $this->postJson("/api/v1/leads/{$id}/assign", ['user_id' => $outsider->id])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
        $this->postJson("/api/v1/leads/{$id}/assign", ['user_id' => $viewer->id])->assertOk()->assertJsonPath('data.assignee.id', $viewer->id);

        $this->getJson("/api/v1/leads/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/leads/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->postJson("/api/v1/leads/{$foreign->id}/convert")->assertNotFound();

        $this->actAs($viewer, $company);
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/leads', ['name' => 'Nope'])->assertForbidden();
        $this->postJson("/api/v1/leads/{$id}/convert")->assertForbidden();
        $this->assertDatabaseMissing('leads', ['name' => 'Nope']);
    }

    public function test_board_groups_opportunities_by_stage_with_totals(): void
    {
        [$user, $company, $stages] = $this->crmUser();
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['New']->id, 'value' => '100.25']);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['New']->id, 'value' => '200']);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['Proposal']->id, 'value' => '50']);
        Opportunity::factory()->create(['value' => '999999']); // other company

        $response = $this->actAs($user, $company)->getJson('/api/v1/opportunities/board');

        $response->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.stage.name', 'New')
            ->assertJsonPath('data.0.count', 2)
            ->assertJsonPath('data.0.total_value', '300.2500')
            ->assertJsonCount(2, 'data.0.items')
            ->assertJsonPath('data.0.items.0.value', '200.0000')
            ->assertJsonPath('data.1.count', 0)
            ->assertJsonPath('data.2.stage.name', 'Proposal')
            ->assertJsonPath('data.2.total_value', '50.0000');
    }

    public function test_moving_to_won_or_lost_stage_closes_the_opportunity(): void
    {
        $this->travelTo('2026-10-14 12:00:00');
        [$user, $company, $stages] = $this->crmUser();
        $opp = Opportunity::factory()->for($company)->create(['stage_id' => $stages['Proposal']->id, 'probability' => 60]);
        $foreignStage = PipelineStage::factory()->create();

        $this->actAs($user, $company);
        $this->patchJson("/api/v1/opportunities/{$opp->id}/stage", ['stage_id' => $stages['Won']->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'won')
            ->assertJsonPath('data.probability', 100)
            ->assertJsonPath('data.closed_at', '2026-10-14T12:00:00.000000Z');

        $this->patchJson("/api/v1/opportunities/{$opp->id}/stage", ['stage_id' => $stages['Lost']->id, 'lost_reason' => 'Budget'])
            ->assertOk()->assertJsonPath('data.status', 'lost')->assertJsonPath('data.lost_reason', 'Budget');

        $this->patchJson("/api/v1/opportunities/{$opp->id}/stage", ['stage_id' => $stages['New']->id])
            ->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.closed_at', null);

        $this->patchJson("/api/v1/opportunities/{$opp->id}/stage", ['stage_id' => $foreignStage->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['stage_id']);
    }

    public function test_opportunity_create_defaults_probability_from_stage_and_allows_override(): void
    {
        [$user, $company, $stages] = $this->crmUser();
        $this->actAs($user, $company);

        $this->postJson('/api/v1/opportunities', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'stage_id']);

        $this->postJson('/api/v1/opportunities', ['title' => 'Deal', 'stage_id' => $stages['Negotiation']->id, 'value' => 1000])
            ->assertCreated()->assertJsonPath('data.probability', 80)->assertJsonPath('data.owner.id', $user->id)->assertJsonPath('data.currency', 'USD');

        $this->postJson('/api/v1/opportunities', ['title' => 'Deal 2', 'stage_id' => $stages['Negotiation']->id, 'probability' => 65])
            ->assertCreated()->assertJsonPath('data.probability', 65);
    }

    public function test_pipeline_summary_weights_values_and_computes_win_rate(): void
    {
        $this->travelTo('2026-10-14 12:00:00');
        [$user, $company, $stages] = $this->crmUser(['leads.view', 'reports.view']);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['Qualified']->id, 'value' => '1000', 'probability' => 30]);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['Proposal']->id, 'value' => '500', 'probability' => 60]);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['Won']->id, 'value' => '700', 'probability' => 100, 'status' => 'won', 'closed_at' => now()->subDays(10)]);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['Lost']->id, 'value' => '300', 'probability' => 0, 'status' => 'lost', 'closed_at' => now()->subDays(20)]);
        Opportunity::factory()->for($company)->create(['stage_id' => $stages['Lost']->id, 'value' => '300', 'probability' => 0, 'status' => 'lost', 'closed_at' => now()->subDays(200)]);

        $this->actAs($user, $company);

        foreach (['/api/v1/sales/pipeline-summary', '/api/v1/sales/reports/pipeline'] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonPath('data.open.count', 2)
                ->assertJsonPath('data.open.total_value', '1500.0000')
                ->assertJsonPath('data.open.weighted_value', '600.0000')
                ->assertJsonPath('data.stages.1.stage.name', 'Qualified')
                ->assertJsonPath('data.stages.1.weighted_value', '300.0000')
                ->assertJsonPath('data.win_rate_90d.won', 1)
                ->assertJsonPath('data.win_rate_90d.lost', 1)
                ->assertJsonPath('data.win_rate_90d.rate', '50.00');
        }
    }

    public function test_pipeline_stage_crud_and_reorder_require_leads_manage(): void
    {
        [$user, $company, $stages] = $this->crmUser();
        [$viewer] = $this->userWith(['leads.view'], $company);
        $foreign = PipelineStage::factory()->create();

        $this->actAs($user, $company);
        $this->postJson('/api/v1/pipeline-stages', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $new = $this->postJson('/api/v1/pipeline-stages', ['name' => 'Demo', 'probability' => 45, 'color' => '#112233'])
            ->assertCreated()->assertJsonPath('data.position', 7)->json('data.id');

        $order = [$new, $stages['New']->id, $stages['Qualified']->id];
        $this->postJson('/api/v1/pipeline-stages/reorder', ['ids' => $order])->assertOk()->assertJsonPath('data.0.id', $new);
        $this->assertDatabaseHas('pipeline_stages', ['id' => $stages['Qualified']->id, 'position' => 3]);
        $this->postJson('/api/v1/pipeline-stages/reorder', ['ids' => [$foreign->id]])->assertStatus(409);

        $this->getJson("/api/v1/pipeline-stages/{$foreign->id}")->assertNotFound();
        Opportunity::factory()->for($company)->create(['stage_id' => $new]);
        $this->deleteJson("/api/v1/pipeline-stages/{$new}")->assertStatus(409);

        $this->actAs($viewer, $company);
        $this->getJson('/api/v1/pipeline-stages')->assertOk()->assertJsonCount(7, 'data');
        $this->postJson('/api/v1/pipeline-stages', ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/pipeline-stages/reorder', ['ids' => $order])->assertForbidden();
    }
}
