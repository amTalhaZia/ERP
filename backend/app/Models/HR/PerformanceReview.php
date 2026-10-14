<?php

namespace App\Models\HR;

use App\Enums\HR\ReviewStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\HR\PerformanceReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'employee_id', 'reviewer_id', 'cycle', 'period_start', 'period_end', 'status', 'overall_rating',
    'ratings', 'strengths', 'improvements', 'goals', 'submitted_at', 'acknowledged_at',
])]
class PerformanceReview extends Model
{
    /** @use HasFactory<PerformanceReviewFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** @var array<int, string> */
    protected array $auditExclude = ['ratings', 'strengths', 'improvements', 'goals'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => ReviewStatus::class,
            'overall_rating' => 'decimal:1',
            'ratings' => 'array',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
