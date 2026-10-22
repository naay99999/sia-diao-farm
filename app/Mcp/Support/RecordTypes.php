<?php

namespace App\Mcp\Support;

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

/**
 * The one place that maps the record type an AI client sends to its model.
 */
class RecordTypes
{
    /** Types shown by list_records; notes have their own list_notes tool. */
    public const LISTABLE = ['care', 'harvest', 'sale', 'expense', 'observation', 'issue'];

    /** Types the delete and restore tools accept. */
    public const DELETABLE = [...self::LISTABLE, 'note'];

    /**
     * @return class-string<Model>
     *
     * @throws ValidationException
     */
    public static function modelClass(string $type): string
    {
        return match ($type) {
            'care' => CareRecord::class,
            'harvest' => Harvest::class,
            'sale' => Sale::class,
            'expense' => Expense::class,
            'observation' => Observation::class,
            'issue' => TreeIssue::class,
            'note' => FarmNote::class,
            default => throw ValidationException::withMessages(['type' => 'type must be one of: '.implode(', ', self::DELETABLE).'.']),
        };
    }

    /**
     * Human wording for error messages, for example "care record".
     */
    public static function label(string $type): string
    {
        return match ($type) {
            'care' => 'care record',
            'issue' => 'issue',
            default => $type,
        };
    }

    /**
     * Find a record of the given type whether or not it is soft-deleted.
     *
     * @throws ValidationException
     */
    public static function findWithTrashed(string $type, int $id): ?Model
    {
        /** @var Builder<Model> $query */
        $query = self::modelClass($type)::query();

        return $query->withoutGlobalScope(SoftDeletingScope::class)->find($id);
    }

    /**
     * Bring a soft-deleted record back; every type in this class uses soft deletes.
     */
    public static function restore(Model $record): void
    {
        if (method_exists($record, 'restore')) {
            $record->restore();
        }
    }
}
