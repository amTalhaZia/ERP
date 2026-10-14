<?php

namespace App\Enums\Projects;

/** Kanban columns, in board order. */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::InReview => 'In review',
            self::Done => 'Done',
            self::Blocked => 'Blocked',
        };
    }
}
