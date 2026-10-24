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

class TrashController extends Controller
{
    private const LIMIT = 200;

    public function index(): Response
    {
        $items = collect(RecordTypes::DELETABLE)
            ->flatMap(function (string $type): array {
                $model = new (RecordTypes::modelClass($type));

                return $model->newQuery()
                    ->onlyTrashed()
                    ->with(RecordSummary::relations($model))
                    ->orderByDesc('deleted_at')
                    ->limit(self::LIMIT)
                    ->get()
                    ->map(fn (Model $record): array => [
                        'type' => $type,
                        'id' => $record->getKey(),
                        ...RecordSummary::for($record),
                        'deletedAt' => $record->getAttribute('deleted_at')->toDateTimeString(),
                    ])
                    ->all();
            })
            ->sortByDesc('deletedAt')
            ->take(self::LIMIT)
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
