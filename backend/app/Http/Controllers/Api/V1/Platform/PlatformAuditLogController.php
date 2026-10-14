<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\Access\AuditLogController;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Access\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Cross-company audit trail (read-only), including platform-level entries. */
class PlatformAuditLogController extends ApiController
{
    private const PERMISSION = 'platform.system.view';

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePlatform($request);

        $query = AuditLog::query()->with(['actor:id,name', 'company:id,name']);

        AuditLogController::applyAuditFilters($query, $request);

        // filter[company_id]=none → platform-level entries only.
        $companyId = $request->input('filter.company_id');
        if ($companyId === 'none') {
            $query->whereNull('audit_logs.company_id');
        } elseif (is_numeric($companyId)) {
            $query->where('audit_logs.company_id', (int) $companyId);
        }

        $this->applySearch($query, $request, ['entity_label', 'action', 'actor_name']);
        $this->applyFilters($query, $request, ['entity_type', 'actor_id', 'entity_id']);
        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['created_at', 'action', 'entity_type', 'company_id'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), AuditLogResource::class);
    }

    public function show(Request $request, int $auditLog): AuditLogResource
    {
        $this->authorizePlatform($request);

        return new AuditLogResource(AuditLog::query()->with(['actor:id,name', 'company:id,name'])->findOrFail($auditLog));
    }

    private function authorizePlatform(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        Gate::allowIf($user->hasPermission(self::PERMISSION));
    }
}
