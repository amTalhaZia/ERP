<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Company;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\Supplier;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A member whose membership carries branch_ids = [Boston] only sees Boston
 * and company-wide (branch NULL) records, is refused Chicago records by id,
 * and cannot create records for Chicago. Unrestricted members see everything.
 */
class BranchRestrictionTest extends TestCase
{
    private const PERMISSIONS = [
        'employees.view', 'employees.create', 'employees.update',
        'attendance.view', 'attendance.manage',
        'leave.view', 'leave.approve',
        'requisitions.view', 'requisitions.create',
        'purchase_orders.view', 'purchase_orders.manage',
        'sales_orders.view', 'sales_orders.manage',
        'inventory.view', 'inventory.receive',
    ];

    private Company $company;

    private Branch $boston;

    private Branch $chicago;

    private User $restricted;

    private User $unrestricted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->boston = Branch::factory()->for($this->company)->create(['name' => 'Boston']);
        $this->chicago = Branch::factory()->for($this->company)->create(['name' => 'Chicago']);
        $this->restricted = User::factory()->inCompany($this->company, self::PERMISSIONS, branchIds: [$this->boston->id])->create();
        $this->unrestricted = User::factory()->inCompany($this->company, self::PERMISSIONS)->create();
    }

    /**
     * @return array{boston: Employee, chicago: Employee, none: Employee}
     */
    private function employees(): array
    {
        return [
            'boston' => Employee::factory()->for($this->company)->create(['branch_id' => $this->boston->id]),
            'chicago' => Employee::factory()->for($this->company)->create(['branch_id' => $this->chicago->id]),
            'none' => Employee::factory()->for($this->company)->create(['branch_id' => null]),
        ];
    }

    /**
     * @return array{boston: Warehouse, chicago: Warehouse, none: Warehouse}
     */
    private function warehouses(): array
    {
        return [
            'boston' => Warehouse::factory()->for($this->company)->create(['branch_id' => $this->boston->id]),
            'chicago' => Warehouse::factory()->for($this->company)->create(['branch_id' => $this->chicago->id]),
            'none' => Warehouse::factory()->for($this->company)->create(['branch_id' => null]),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function ids(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /**
     * @param  array{boston: object, chicago: object, none: object}  $rows
     */
    private function assertListsRespectBranches(string $url, array $rows): void
    {
        $expectedRestricted = collect([$rows['boston']->id, $rows['none']->id])->sort()->values()->all();
        $expectedAll = collect([$rows['boston']->id, $rows['chicago']->id, $rows['none']->id])->sort()->values()->all();

        $this->assertSame($expectedRestricted, $this->ids($this->actingAsIn($this->restricted, $this->company)->getJson($url)));
        $this->assertSame($expectedAll, $this->ids($this->actingAsIn($this->unrestricted, $this->company)->getJson($url)));
    }

    private function assertRecordRefused(string $url): void
    {
        $this->actingAsIn($this->restricted, $this->company)->getJson($url)->assertForbidden();
        $this->actingAsIn($this->unrestricted, $this->company)->getJson($url)->assertOk();
    }

    // -- HR -----------------------------------------------------------------

    public function test_employees_are_restricted_to_allowed_branches(): void
    {
        $rows = $this->employees();

        $this->assertListsRespectBranches('/api/v1/employees', $rows);
        $this->assertRecordRefused("/api/v1/employees/{$rows['chicago']->id}");
        $this->actingAsIn($this->restricted, $this->company)->getJson("/api/v1/employees/{$rows['boston']->id}")->assertOk();

        $directory = collect($this->actingAsIn($this->restricted, $this->company)->getJson('/api/v1/employees/directory')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($directory->contains($rows['chicago']->id));

        $this->actingAsIn($this->restricted, $this->company)
            ->putJson("/api/v1/employees/{$rows['chicago']->id}", ['first_name' => 'Hijacked'])
            ->assertForbidden();
        $this->assertDatabaseMissing('employees', ['id' => $rows['chicago']->id, 'first_name' => 'Hijacked']);
    }

    public function test_cannot_create_an_employee_in_an_inaccessible_branch(): void
    {
        $payload = [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com',
            'employment_type' => 'full_time', 'hired_at' => '2026-01-05',
        ];

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/employees', $payload + ['branch_id' => $this->chicago->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseMissing('employees', ['email' => 'ada@example.com']);
    }

    public function test_attendance_is_restricted_via_the_employee_branch(): void
    {
        $employees = $this->employees();
        $rows = array_map(fn (Employee $e) => AttendanceRecord::factory()->create(['employee_id' => $e->id]), $employees);

        $this->assertListsRespectBranches('/api/v1/attendance', $rows);
        $this->assertRecordRefused("/api/v1/attendance/{$rows['chicago']->id}");

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/attendance', ['employee_id' => $employees['chicago']->id, 'date' => '2026-10-01', 'status' => 'present'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/attendance', ['employee_id' => $employees['boston']->id, 'date' => '2026-10-01', 'status' => 'present'])
            ->assertSuccessful();
    }

    public function test_leave_requests_are_restricted_via_the_employee_branch(): void
    {
        $leaveType = LeaveType::factory()->for($this->company)->create();
        $rows = array_map(
            fn (Employee $e) => LeaveRequest::factory()->create(['employee_id' => $e->id, 'leave_type_id' => $leaveType->id]),
            $this->employees(),
        );

        $this->assertListsRespectBranches('/api/v1/leave-requests', $rows);
        $this->assertRecordRefused("/api/v1/leave-requests/{$rows['chicago']->id}");

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson("/api/v1/leave-requests/{$rows['chicago']->id}/approve")
            ->assertForbidden();
    }

    // -- Procurement ----------------------------------------------------------

    public function test_purchase_requisitions_are_restricted_to_allowed_branches(): void
    {
        $rows = [
            'boston' => PurchaseRequisition::factory()->for($this->company)->create(['branch_id' => $this->boston->id]),
            'chicago' => PurchaseRequisition::factory()->for($this->company)->create(['branch_id' => $this->chicago->id]),
            'none' => PurchaseRequisition::factory()->for($this->company)->create(['branch_id' => null]),
        ];

        $this->assertListsRespectBranches('/api/v1/requisitions', $rows);
        $this->assertRecordRefused("/api/v1/requisitions/{$rows['chicago']->id}");

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/requisitions', [
                'branch_id' => $this->chicago->id,
                'lines' => [['description' => 'Paper', 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('branch_id');
    }

    public function test_purchase_orders_are_restricted_to_allowed_branches(): void
    {
        $rows = [
            'boston' => PurchaseOrder::factory()->for($this->company)->create(['branch_id' => $this->boston->id]),
            'chicago' => PurchaseOrder::factory()->for($this->company)->create(['branch_id' => $this->chicago->id]),
            'none' => PurchaseOrder::factory()->for($this->company)->create(['branch_id' => null]),
        ];

        $this->assertListsRespectBranches('/api/v1/purchase-orders', $rows);
        $this->assertRecordRefused("/api/v1/purchase-orders/{$rows['chicago']->id}");

        $supplier = Supplier::factory()->for($this->company)->create();
        $warehouses = $this->warehouses();

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/purchase-orders', [
                'supplier_id' => $supplier->id,
                'branch_id' => $this->chicago->id,
                'warehouse_id' => $warehouses['chicago']->id,
                'order_date' => '2026-10-01',
                'lines' => [['description' => 'Paper', 'quantity' => 1, 'unit_price' => 10]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id', 'warehouse_id']);
    }

    // -- Sales ------------------------------------------------------------------

    public function test_sales_orders_are_restricted_via_the_warehouse_branch(): void
    {
        $warehouses = $this->warehouses();
        $rows = [
            'boston' => SalesOrder::factory()->for($this->company)->create(['warehouse_id' => $warehouses['boston']->id]),
            'chicago' => SalesOrder::factory()->for($this->company)->create(['warehouse_id' => $warehouses['chicago']->id]),
            'none' => SalesOrder::factory()->for($this->company)->create(['warehouse_id' => null]),
        ];

        $this->assertListsRespectBranches('/api/v1/sales-orders', $rows);
        $this->assertRecordRefused("/api/v1/sales-orders/{$rows['chicago']->id}");

        $customer = Customer::factory()->for($this->company)->create();

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/sales-orders', ['customer_id' => $customer->id, 'warehouse_id' => $warehouses['chicago']->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('warehouse_id');
    }

    // -- Inventory --------------------------------------------------------------

    public function test_stock_levels_are_restricted_via_the_warehouse_branch(): void
    {
        $warehouses = $this->warehouses();
        $product = Product::factory()->for($this->company)->create();
        $rows = array_map(
            fn (Warehouse $w) => StockLevel::factory()->create(['product_id' => $product->id, 'warehouse_id' => $w->id, 'quantity_on_hand' => '5.0000']),
            $warehouses,
        );

        $this->assertListsRespectBranches('/api/v1/stock/levels', $rows);

        $this->actingAsIn($this->restricted, $this->company)
            ->postJson('/api/v1/stock/receive', [
                'warehouse_id' => $warehouses['chicago']->id,
                'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 2]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('warehouse_id');
    }
}
