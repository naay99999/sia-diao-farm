<?php

namespace App\Support;

use App\Enums\RestoreConflict;
use App\Mcp\Support\RecordTypes;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The one rule for bringing a soft-deleted record back, shared by the app and the MCP tools.
 */
class RecordRestorer
{
    /**
     * Why the record cannot be restored right now, or null when it can.
     */
    public static function conflict(Model $record): ?RestoreConflict
    {
        if ($record->getAttribute('deleted_at') === null) {
            return RestoreConflict::NotDeleted;
        }

        if ($record instanceof TreeIssue && $record->isOpen() && self::hasOtherOpenIssue($record)) {
            return RestoreConflict::OpenIssueExists;
        }

        return null;
    }

    /**
     * @throws LogicException when the record has a restore conflict
     */
    public static function restore(Model $record): void
    {
        $conflict = self::conflict($record);

        if ($conflict !== null) {
            throw new LogicException("Record cannot be restored: {$conflict->value}.");
        }

        RecordTypes::restore($record);
    }

    /**
     * Restoring an open issue next to another open one for the same tree and finding would split
     * one ongoing problem into two.
     */
    private static function hasOtherOpenIssue(TreeIssue $issue): bool
    {
        return TreeIssue::query()
            ->open()
            ->where('tree_id', $issue->tree_id)
            ->where('finding_id', $issue->finding_id)
            ->whereKeyNot($issue->id)
            ->exists();
    }
}
