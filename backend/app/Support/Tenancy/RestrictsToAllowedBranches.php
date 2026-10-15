<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Branch restriction for tenant models. A member whose company membership
 * carries `branch_ids` may only see/act on rows of those branches; rows with
 * no branch (NULL) are company-wide and stay visible.
 *
 * Configure per model (both optional):
 *
 *   protected string $branchColumn = 'branch_id';         // direct column
 *   protected array $branchRelations = ['employee'];      // or via BelongsTo parent(s)
 *
 * A model with `$branchRelations` and no `$branchColumn` derives its branch
 * from the parent(s): the row is visible when the parent key is NULL or the
 * parent is itself visible (the parent model must use this trait). With
 * several relations (e.g. a transfer's from/to warehouses) ANY visible
 * parent makes the row visible.
 *
 * Lists: `Model::query()->visibleBranches()`.
 * Single records (policies): `$model->isBranchAccessible()`.
 */
trait RestrictsToAllowedBranches
{
    public function branchColumn(): ?string
    {
        if (property_exists($this, 'branchColumn')) {
            return $this->branchColumn;
        }

        return $this->branchRelations() === [] ? 'branch_id' : null;
    }

    /**
     * @return array<int, string>
     */
    public function branchRelations(): array
    {
        return property_exists($this, 'branchRelations') ? $this->branchRelations : [];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleBranches(Builder $query): Builder
    {
        $allowed = app(TenantContext::class)->allowedBranchIds();

        if ($allowed === null) {
            return $query;
        }

        $column = $this->branchColumn();
        $relations = $this->branchRelations();

        return $query->where(function (Builder $q) use ($allowed, $column, $relations): void {
            if ($column !== null) {
                $qualified = $this->qualifyColumn($column);
                $q->orWhere(fn (Builder $w) => $w->whereIn($qualified, $allowed)->orWhereNull($qualified));
            }

            foreach ($relations as $relation) {
                /** @var BelongsTo $rel */
                $rel = $this->{$relation}();
                $foreignKey = $rel->getQualifiedForeignKeyName();

                $q->orWhere(fn (Builder $w) => $w->whereNull($foreignKey)
                    ->orWhereHas($relation, fn (Builder $parent) => $parent->visibleBranches()));
            }
        });
    }

    /** Is this record inside the acting user's branch restriction? */
    public function isBranchAccessible(): bool
    {
        $tenant = app(TenantContext::class);

        if ($tenant->allowedBranchIds() === null) {
            return true;
        }

        $column = $this->branchColumn();

        if ($column !== null && $this->branchRelations() === []) {
            $branchId = $this->getAttribute($column);

            return $tenant->canAccessBranch($branchId === null ? null : (int) $branchId);
        }

        if (! $this->exists) {
            return true;
        }

        // Query instead of relations so policies never trigger lazy loading.
        $query = $this->newQueryWithoutScopes()->whereKey($this->getKey());

        return $this->scopeVisibleBranches($query)->exists();
    }
}
