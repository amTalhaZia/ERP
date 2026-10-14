<?php

namespace App\Models\Projects;

use App\Models\Documents\Document;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Projects\ProjectExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cost booked against a project. Tracked for project profitability only;
 * posting to the general ledger is out of scope (use Finance bills/claims).
 */
#[Fillable(['company_id', 'project_id', 'date', 'category', 'description', 'amount', 'currency', 'receipt_document_id', 'created_by'])]
class ProjectExpense extends Model
{
    /** @use HasFactory<ProjectExpenseFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'receipt_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
