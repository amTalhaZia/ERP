<?php

namespace App\Actions\Access;

use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Expands a role template's permission patterns from config/erp.php into
 * concrete slugs. Supports: exact slug, `module.*`, `*.view`, `*` (all
 * non-platform), and `!slug` exclusions applied last.
 */
class ResolveTemplatePermissions
{
    /**
     * @param  array<int, string>  $patterns
     * @return Collection<int, string>
     */
    public function handle(array $patterns): Collection
    {
        $all = Permission::query()->where('is_platform', false)->pluck('slug');

        $included = collect();
        $excluded = collect();

        foreach ($patterns as $pattern) {
            if (str_starts_with($pattern, '!')) {
                $excluded = $excluded->merge($this->match($all, substr($pattern, 1)));
            } else {
                $included = $included->merge($this->match($all, $pattern));
            }
        }

        return $included->diff($excluded)->unique()->values();
    }

    /**
     * @param  Collection<int, string>  $all
     * @return Collection<int, string>
     */
    private function match(Collection $all, string $pattern): Collection
    {
        if ($pattern === '*') {
            return $all;
        }

        return $all->filter(fn (string $slug) => Str::is($pattern, $slug))->values();
    }
}
