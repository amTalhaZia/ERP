<?php

namespace Tests\Feature\Search;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Company;
use App\Models\Documents\Document;
use App\Models\Finance\Invoice;
use App\Models\Inventory\Product;
use App\Models\Procurement\Supplier;
use App\Models\Sales\Customer;
use App\Models\User;
use Tests\Feature\Dashboard\Concerns\BuildsInsightsData;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use BuildsInsightsData;

    /**
     * "Acme" records of several types in two companies.
     *
     * @return array{0: Company, 1: Company}
     */
    private function data(): array
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();

        foreach ([$company, $other] as $tenant) {
            $suffix = $tenant->is($company) ? 'Mine' : 'Theirs';
            $customer = Customer::factory()->create(['company_id' => $tenant->id, 'name' => "Acme {$suffix} Ltd"]);
            Supplier::factory()->create(['company_id' => $tenant->id, 'name' => "Acme {$suffix} Supplies"]);
            Product::factory()->create(['company_id' => $tenant->id, 'name' => "Acme {$suffix} Widget", 'sku' => 'ACME-'.$suffix]);
            $this->employee($tenant, ['first_name' => 'Acme', 'last_name' => $suffix]);
            Invoice::factory()->create(['company_id' => $tenant->id, 'customer_id' => $customer->id, 'number' => 'INV-ACME-'.$suffix, 'status' => InvoiceStatus::Draft]);
        }

        return [$company, $other];
    }

    public function test_returns_only_permitted_types_from_the_current_tenant(): void
    {
        [$company] = $this->data();
        [$user] = $this->userWith(['customers.view', 'employees.view', 'invoices.view'], $company);

        $response = $this->actAs($user, $company)->getJson('/api/v1/search?q=acme')
            ->assertOk()
            ->assertJsonStructure(['data' => [['type', 'id', 'title', 'subtitle', 'url']], 'meta' => ['query', 'types']]);

        $results = collect($response->json('data'));
        $this->assertEqualsCanonicalizing(['customer', 'employee', 'invoice'], $results->pluck('type')->unique()->values()->all());
        $this->assertStringNotContainsString('Theirs', $response->getContent());
        $this->assertSame(['employees', 'customers', 'invoices'], $response->json('meta.types'));

        $customer = $results->firstWhere('type', 'customer');
        $this->assertSame('Acme Mine Ltd', $customer['title']);
        $this->assertSame('/sales/customers/'.$customer['id'], $customer['url']);

        $invoice = $results->firstWhere('type', 'invoice');
        $this->assertSame('INV-ACME-Mine', $invoice['title']);
        $this->assertStringContainsString('Acme Mine Ltd', $invoice['subtitle']);
    }

    public function test_user_without_module_permissions_gets_no_results(): void
    {
        [$company] = $this->data();
        [$user] = $this->userWith(['dashboard.view'], $company);

        $this->actAs($user, $company)->getJson('/api/v1/search?q=acme')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.types', []);
    }

    public function test_types_filter_and_product_sku_search(): void
    {
        [$company] = $this->data();
        [$user] = $this->userWith(['products.view', 'suppliers.view'], $company);

        $this->actAs($user, $company)->getJson('/api/v1/search?q=ACME-Mi&types=products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'product')
            ->assertJsonPath('data.0.subtitle', fn (string $subtitle) => str_starts_with($subtitle, 'ACME-Mine'));

        $this->getJson('/api/v1/search?q=acme&types=bogus')->assertStatus(422);
    }

    public function test_limits_results_per_type(): void
    {
        $company = Company::factory()->create();
        Customer::factory()->count(8)->sequence(fn ($s) => ['name' => 'Bulk Customer '.$s->index])->create(['company_id' => $company->id]);
        [$user] = $this->userWith(['customers.view'], $company);

        $this->actAs($user, $company)->getJson('/api/v1/search?q=bulk')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_like_wildcards_are_escaped(): void
    {
        [$company] = $this->data();
        [$user] = $this->userWith(['customers.view'], $company);

        $this->actAs($user, $company)->getJson('/api/v1/search?q=%25%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/search?q=__')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_documents_respect_visibility(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->inCompany($company)->create();
        Document::factory()->create(['company_id' => $company->id, 'title' => 'Handbook public']);
        Document::factory()->private()->ownedBy($owner)->create(['company_id' => $company->id, 'title' => 'Handbook secret']);
        [$user] = $this->userWith(['documents.view'], $company);

        $this->actAs($user, $company)->getJson('/api/v1/search?q=handbook')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Handbook public');
    }

    public function test_query_must_have_at_least_two_characters(): void
    {
        [$user, $company] = $this->userWith([]);

        $this->actAs($user, $company)->getJson('/api/v1/search?q=a')->assertStatus(422)->assertJsonValidationErrors('q');
        $this->getJson('/api/v1/search')->assertStatus(422);
    }
}
