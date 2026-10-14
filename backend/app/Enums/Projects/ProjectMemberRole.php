<?php

namespace App\Enums\Projects;

enum ProjectMemberRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';
}
