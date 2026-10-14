<?php

namespace App\Models\Sales;

use App\Enums\Sales\OpportunityStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'title', 'customer_id', 'lead_id', 'stage_id', 'value', 'currency', 'probability',
    'expected_close_date', 'status', 'lost_reason', 'owner_id', 'closed_at',
])]
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    /** @var array<string, mixed> mirrors the column defaults so fresh models serialise fully */
    protected $attributes = [
        'value' => '0',
        'probability' => 0,
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'status' => OpportunityStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Apply a stage: probability follows the stage and won/lost stages close
     * the opportunity (a move back to an open stage re-opens it).
     */
    public function applyStage(PipelineStage $stage, ?string $lostReason = null): void
    {
        $this->stage_id = $stage->id;
        $this->probability = $stage->probability;

        if ($stage->is_won) {
            $this->status = OpportunityStatus::Won;
            $this->closed_at ??= now();
            $this->lost_reason = null;
        } elseif ($stage->is_lost) {
            $this->status = OpportunityStatus::Lost;
            $this->closed_at ??= now();
            $this->lost_reason = $lostReason ?? $this->lost_reason;
        } else {
            $this->status = OpportunityStatus::Open;
            $this->closed_at = null;
            $this->lost_reason = null;
        }
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
