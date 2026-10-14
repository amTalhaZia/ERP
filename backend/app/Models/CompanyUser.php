<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of a user in a company. Deliberately NOT tenant-scoped: it is
 * the table the tenant resolver reads before any tenant exists.
 */
#[Fillable(['company_id', 'user_id', 'is_default', 'is_active', 'branch_ids', 'joined_at'])]
class CompanyUser extends Model
{
    protected $table = 'company_user';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'branch_ids' => 'array',
            'joined_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
