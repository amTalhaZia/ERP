<?php

namespace App\Models\HR;

use App\Enums\HR\DocumentCategory;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\HR\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'employee_id', 'title', 'category', 'file_path', 'mime', 'size_bytes', 'expires_at',
    'uploaded_by', 'is_confidential',
])]
class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    public const DISK = 'local';

    /** @var array<int, string> */
    protected array $auditExclude = ['file_path'];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'size_bytes' => 'integer',
            'expires_at' => 'date',
            'is_confidential' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
