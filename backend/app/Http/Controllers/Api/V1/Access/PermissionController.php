<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The permission catalogue, grouped by module in the seeder's declared order
 * and joined with database ids. Platform permissions are never offered to
 * company roles.
 */
class PermissionController extends ApiController
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $ids = Permission::query()->where('is_platform', false)->pluck('id', 'slug');

        $modules = [];

        foreach (PermissionSeeder::catalogue() as $module => $permissions) {
            if ($module === 'platform') {
                continue;
            }

            $items = [];

            foreach ($permissions as $slug => $description) {
                if (! $ids->has($slug)) {
                    continue;
                }

                $items[] = ['id' => $ids->get($slug), 'slug' => $slug, 'description' => $description];
            }

            if ($items !== []) {
                $modules[] = ['module' => $module, 'permissions' => $items];
            }
        }

        return response()->json(['data' => $modules]);
    }
}
