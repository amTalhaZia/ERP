<?php

namespace App\Models\Projects;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Projects\TaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'task_id', 'user_id', 'body', 'edited_at'])]
class TaskComment extends Model
{
    /** @use HasFactory<TaskCommentFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
