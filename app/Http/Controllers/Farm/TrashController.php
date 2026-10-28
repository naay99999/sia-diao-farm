<?php

namespace App\Http\Controllers\Farm;

use App\Enums\RestoreConflict;
use App\Http\Controllers\Controller;
use App\Mcp\Support\RecordTypes;
use App\Support\RecordRestorer;
use App\Support\RecordSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class TrashController extends Controller
{
    private const LIMIT = 200;

    /**
     * The newest deleted records of every type. Only ids and deletion times are read for the cut, then the
     * records that make it are loaded with what their summary needs.
     */
    public function index(): Response
    {
        $newest = collect(RecordTypes::DELETABLE)
            ->flatMap(fn (string $type): array => RecordTypes::trashedQuery($type)
                ->orderByDesc('deleted_at')
                ->limit(self::LIMIT)
                ->toBase()
                ->get(['id', 'deleted_at'])
                ->map(fn (stdClass $row): array => ['type' => $type, 'id' => (int) $row->id, 'deletedAt' => $row->deleted_at])
                ->all())
            ->sortByDesc('deletedAt')
            ->take(self::LIMIT);

        $items = $newest
            ->groupBy('type')
            ->flatMap(function ($rows, string $type): array {
                $model = new (RecordTypes::modelClass($type));

                return RecordTypes::trashedQuery($type)
                    ->with(RecordSummary::relations($model))
                    ->whereKey($rows->pluck('id'))
                    ->get()
                    ->map(fn (Model $record): array => [
                        'type' => $type,
                        'id' => $record->getKey(),
                        ...RecordSummary::for($record),
                        'deletedAt' => $record->getAttribute('deleted_at')->toDateTimeString(),
                    ])
                    ->all();
            })
            ->sortBy([['deletedAt', 'desc'], ['id', 'desc']])
            ->values()
            ->all();

        return Inertia::render('records/trash', ['items' => $items]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        $record = RecordTypes::findWithTrashed($type, $id);

        abort_if($record === null, 404);

        $conflict = RecordRestorer::conflict($record);

        if ($conflict !== null) {
            throw ValidationException::withMessages(['id' => match ($conflict) {
                RestoreConflict::NotDeleted => 'บันทึกนี้ไม่ได้อยู่ในถังขยะ',
                RestoreConflict::OpenIssueExists => 'ต้นนี้มีปัญหาเดียวกันที่ยังเปิดอยู่ ให้บันทึกการดูซ้ำในเรื่องเดิมแทน',
            }]);
        }

        RecordRestorer::restore($record);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'กู้คืนแล้ว']);

        return to_route('records.trash');
    }
}
