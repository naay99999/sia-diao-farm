<?php

namespace App\Support;

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One-line Thai description of any farm record: what kind it is, what it says, and who or what it is about.
 */
class RecordSummary
{
    /**
     * The relations {@see self::for()} reads, to eager-load so listing many records stays at constant queries.
     *
     * @return array<int, string>
     */
    public static function relations(Model $record): array
    {
        return match (true) {
            $record instanceof CareRecord, $record instanceof Sale => ['orchard'],
            $record instanceof Harvest => ['tree'],
            $record instanceof Observation, $record instanceof TreeIssue => ['tree', 'finding'],
            $record instanceof Expense => ['season.orchard'],
            $record instanceof FarmNote => ['tree', 'finding'],
            default => [],
        };
    }

    /**
     * @return array{kind: string, title: string, subject: string, href: string|null}
     */
    public static function for(Model $record): array
    {
        return match (true) {
            $record instanceof CareRecord => [
                'kind' => 'การดูแล',
                'title' => trim($record->type->label().' '.$record->product),
                'subject' => $record->orchard->name,
                'href' => route('orchards.show', $record->orchard, false),
            ],
            $record instanceof Harvest => [
                'kind' => 'ผลผลิต',
                'title' => 'ผลผลิต '.(float) $record->weight_kg.' กก.',
                'subject' => 'ต้น '.$record->tree->code,
                'href' => route('trees.show', $record->tree, false),
            ],
            $record instanceof Observation => [
                'kind' => 'สภาพต้น',
                'title' => $record->outcome === null
                    ? $record->finding->name
                    : 'ดูซ้ำ: '.$record->finding->name.' — '.$record->outcome->label(),
                'subject' => 'ต้น '.$record->tree->code,
                'href' => route('trees.show', $record->tree, false),
            ],
            $record instanceof Sale => [
                'kind' => 'การขาย',
                'title' => 'ขาย '.(float) $record->weight_kg.' กก.',
                'subject' => $record->orchard->name,
                'href' => route('orchards.show', $record->orchard, false),
            ],
            $record instanceof Expense => [
                'kind' => 'ค่าใช้จ่าย',
                'title' => $record->category->label().' '.number_format((float) $record->amount, 2).' บาท',
                'subject' => $record->season?->orchard->name ?? 'ค่าใช้จ่ายรวม',
                'href' => route('expenses.index', absolute: false),
            ],
            $record instanceof TreeIssue => [
                'kind' => 'ปัญหา',
                'title' => $record->finding->name,
                'subject' => 'ต้น '.$record->tree->code,
                'href' => route('trees.show', $record->tree, false),
            ],
            $record instanceof FarmNote => [
                'kind' => 'โน้ต',
                'title' => Str::limit($record->body, 60),
                'subject' => match (true) {
                    $record->tree !== null => 'ต้น '.$record->tree->code,
                    $record->finding !== null => $record->finding->name,
                    default => 'โน้ตทั่วไป',
                },
                'href' => $record->tree ? route('trees.show', $record->tree, false) : null,
            ],
            default => ['kind' => 'บันทึก', 'title' => '', 'subject' => '', 'href' => null],
        };
    }
}
