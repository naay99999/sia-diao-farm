<?php

namespace App\Support;

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use Illuminate\Support\Str;

/**
 * The latest records an AI client wrote through MCP, newest first, so the owner can see the connection at work.
 */
class RecentAiRecords
{
    /**
     * @return array<int, array{kind:string, title:string, subject:string, recordedOn:string, href:string|null, by:string|null}>
     */
    public static function latest(int $limit = 5): array
    {
        $rows = collect()
            ->merge(CareRecord::query()->with(['orchard', 'creator:id,name'])->latestFromMcp($limit)->get()->map(fn (CareRecord $record): array => self::row(
                'การดูแล',
                trim($record->type->label().' '.$record->product),
                $record->orchard->name,
                route('orchards.show', $record->orchard, false),
                $record,
            )))
            ->merge(Harvest::query()->with(['tree', 'creator:id,name'])->latestFromMcp($limit)->get()->map(fn (Harvest $harvest): array => self::row(
                'ผลผลิต',
                'ผลผลิต '.(float) $harvest->weight_kg.' กก.',
                'ต้น '.$harvest->tree->code,
                route('trees.show', $harvest->tree, false),
                $harvest,
            )))
            ->merge(Observation::query()->with(['tree', 'finding', 'creator:id,name'])->latestFromMcp($limit)->get()->map(fn (Observation $observation): array => self::row(
                'สภาพต้น',
                $observation->outcome === null
                    ? $observation->finding->name
                    : 'ดูซ้ำ: '.$observation->finding->name.' — '.$observation->outcome->label(),
                'ต้น '.$observation->tree->code,
                route('trees.show', $observation->tree, false),
                $observation,
            )))
            ->merge(Sale::query()->with(['orchard', 'creator:id,name'])->latestFromMcp($limit)->get()->map(fn (Sale $sale): array => self::row(
                'การขาย',
                'ขาย '.(float) $sale->weight_kg.' กก.',
                $sale->orchard->name,
                route('orchards.show', $sale->orchard, false),
                $sale,
            )))
            ->merge(Expense::query()->with(['season.orchard', 'creator:id,name'])->latestFromMcp($limit)->get()->map(fn (Expense $expense): array => self::row(
                'ค่าใช้จ่าย',
                $expense->category->label().' '.number_format((float) $expense->amount, 2).' บาท',
                $expense->season?->orchard->name ?? 'ค่าใช้จ่ายรวม',
                route('expenses.index', absolute: false),
                $expense,
            )))
            ->merge(FarmNote::query()->with(['tree', 'finding', 'creator:id,name'])->latestFromMcp($limit)->get()->map(fn (FarmNote $note): array => self::row(
                'โน้ต',
                Str::limit($note->body, 60),
                match (true) {
                    $note->tree !== null => 'ต้น '.$note->tree->code,
                    $note->finding !== null => $note->finding->name,
                    default => 'โน้ตทั่วไป',
                },
                $note->tree ? route('trees.show', $note->tree, false) : null,
                $note,
            )));

        return $rows
            ->sortByDesc('createdAt')
            ->take($limit)
            ->map(fn (array $row): array => [
                'kind' => $row['kind'],
                'title' => $row['title'],
                'subject' => $row['subject'],
                'recordedOn' => $row['recordedOn'],
                'href' => $row['href'],
                'by' => $row['by'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{kind:string, title:string, subject:string, recordedOn:string, href:string|null, by:string|null, createdAt:string}
     */
    private static function row(string $kind, string $title, string $subject, ?string $href, CareRecord|Harvest|Observation|Sale|Expense|FarmNote $record): array
    {
        return [
            'kind' => $kind,
            'title' => $title,
            'subject' => $subject,
            'recordedOn' => $record->created_at?->toDateString() ?? '',
            'href' => $href,
            'by' => $record->creator?->name,
            'createdAt' => $record->created_at?->toDateTimeString() ?? '',
        ];
    }
}
