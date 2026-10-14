<?php

namespace App\Models\Sales;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\PipelineStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'probability', 'position', 'is_won', 'is_lost', 'color'])]
class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** @var array<string, mixed> mirrors the column defaults so fresh models serialise fully */
    protected $attributes = [
        'probability' => 0,
        'is_won' => false,
        'is_lost' => false,
    ];

    /**
     * Default stages seeded for every new company.
     *
     * @return array<int, array{name: string, probability: int, position: int, is_won: bool, is_lost: bool, color: string}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'New', 'probability' => 10, 'position' => 1, 'is_won' => false, 'is_lost' => false, 'color' => '#64748B'],
            ['name' => 'Qualified', 'probability' => 30, 'position' => 2, 'is_won' => false, 'is_lost' => false, 'color' => '#0EA5E9'],
            ['name' => 'Proposal', 'probability' => 60, 'position' => 3, 'is_won' => false, 'is_lost' => false, 'color' => '#8B5CF6'],
            ['name' => 'Negotiation', 'probability' => 80, 'position' => 4, 'is_won' => false, 'is_lost' => false, 'color' => '#F59E0B'],
            ['name' => 'Won', 'probability' => 100, 'position' => 5, 'is_won' => true, 'is_lost' => false, 'color' => '#16A34A'],
            ['name' => 'Lost', 'probability' => 0, 'position' => 6, 'is_won' => false, 'is_lost' => true, 'color' => '#DC2626'],
        ];
    }

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'position' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'stage_id');
    }
}
