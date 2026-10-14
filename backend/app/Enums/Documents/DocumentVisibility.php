<?php

namespace App\Enums\Documents;

/**
 * Company    — everyone holding documents.view in the company.
 * Restricted — the owner, holders of one of `allowed_role_ids`, and documents.manage.
 * Private    — the owner only.
 */
enum DocumentVisibility: string
{
    case Company = 'company';
    case Restricted = 'restricted';
    case Private = 'private';
}
