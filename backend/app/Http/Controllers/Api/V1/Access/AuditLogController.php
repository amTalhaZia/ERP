<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Access\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only view of the current company's audit trail. There are no write
 * routes: the table is append-only.
 */
class AuditLogController extends ApiController
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()
            ->where('audit_logs.company_id', $this->tenant->id())
            ->with('actor:id,name');

        self::applyAuditFilters($query, $request);
        $this->applySearch($query, $request, ['entity_label', 'action', 'actor_name']);
        $this->applyFilters($query, $request, ['entity_type', 'actor_id', 'entity_id']);
        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['created_at', 'action', 'entity_type'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), AuditLogResource::class);
    }

    public function show(int $auditLog): AuditLogResource
    {
        $log = AuditLog::query()
            ->where('company_id', $this->tenant->id())
            ->with('actor:id,name')
            ->findOrFail($auditLog);

        Gate::authorize('view', $log);

        return new AuditLogResource($log);
    }

    /**
     * `filter[action]=auth.` matches every action in that namespace (prefix).
     * Shared with the platform audit controller.
     *
     * @param  Builder<AuditLog>  $query
     */
    public static function applyAuditFilters(Builder $query, Request $request): void
    {
        $action = trim((string) $request->input('filter.action', ''));

        if ($action !== '') {
            $like = str_replace(['%', '_'], ['\\%', '\\_'], $action).'%';
            $query->where('audit_logs.action', 'like', $like);
        }
    }
}
