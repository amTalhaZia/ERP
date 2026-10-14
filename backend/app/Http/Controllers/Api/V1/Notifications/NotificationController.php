<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Notifications\NotificationResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The current user's in-app notifications. Notifications belong to the user,
 * not the tenant; those stamped with a company_id are shown only while that
 * company is active, company-less ones always.
 */
class NotificationController extends ApiController
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->own($request);

        $read = $request->input('filter.read');
        if ($read === 'true' || $read === '1' || $read === true) {
            $query->whereNotNull('read_at');
        } elseif ($read === 'false' || $read === '0' || $read === false) {
            $query->whereNull('read_at');
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request)), NotificationResource::class);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => $this->own($request)->whereNull('read_at')->count()]]);
    }

    public function markRead(Request $request, string $id): NotificationResource
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->own($request)->findOrFail($id);
        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->own($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $count]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->own($request)->findOrFail($id)->delete();

        return $this->noContent();
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function own(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        $query = DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());

        if ($this->tenant->has()) {
            $companyId = $this->tenant->id();

            $query->where(function (Builder $q) use ($companyId): void {
                $q->where('data->company_id', $companyId)->orWhereNull('data->company_id');
            });
        }

        return $query;
    }
}
