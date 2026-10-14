<?php

namespace App\Enums\Projects;

/** Priority shared by projects and tasks. */
enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
