<?php

namespace App\Enums\Projects;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
