<?php

namespace App\Enums;

enum RestoreConflict: string
{
    case NotDeleted = 'not_deleted';
    case OpenIssueExists = 'open_issue_exists';
}
