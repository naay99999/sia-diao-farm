<?php

namespace App\Mcp\Tools;

use App\Enums\RestoreConflict;
use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\RecordTypes;
use App\Support\RecordRestorer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('restore_record')]
#[Description('Only call after the user confirmed a summary in chat. Brings back a record that was deleted (by delete_record or in the app), by type and id. type is one of care, harvest, sale, expense, observation, issue, note. Find deleted ids with list_records or list_notes using include_deleted. Returns the restored record.')]
class RestoreRecord extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(RecordTypes::DELETABLE)->description('Kind of record to restore.')->required(),
            'id' => $schema->integer()->description('Id of the deleted record.')->required(),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'type' => ['required', 'string', 'in:'.implode(',', RecordTypes::DELETABLE)],
            'id' => ['required', 'integer'],
        ], [
            'type.required' => 'type is required; use one of: '.implode(', ', RecordTypes::DELETABLE).'.',
            'type.in' => 'type must be one of: '.implode(', ', RecordTypes::DELETABLE).'.',
            'id.required' => 'id is required; pass the id of the deleted record.',
        ]);

        $type = $validated['type'];
        $id = (int) $validated['id'];
        $label = RecordTypes::label($type);
        $record = RecordTypes::findWithTrashed($type, $id);

        if ($record === null) {
            throw ValidationException::withMessages([
                'id' => "No {$label} with id {$id} exists. Find deleted records with ".($type === 'note' ? 'list_notes' : 'list_records').' and include_deleted true.',
            ]);
        }

        $conflict = RecordRestorer::conflict($record);

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'id' => match ($conflict) {
                    RestoreConflict::NotDeleted => ucfirst($label)." {$id} is not deleted, so there is nothing to restore.",
                    RestoreConflict::OpenIssueExists => "Tree {$record->getAttribute('tree_id')} already has another open issue for the same finding, so issue {$id} cannot be restored as open. Record a follow-up on the open issue instead.",
                },
            ]);
        }

        RecordRestorer::restore($record);

        return Response::json([
            'type' => $type,
            'id' => $id,
            'record' => RecordSerializer::serialize($record),
        ]);
    }
}
