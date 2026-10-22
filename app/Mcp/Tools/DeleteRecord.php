<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordTypes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_record')]
#[Description('Only call after the user confirmed a summary in chat. Deletes one farm record by type and id. This is a soft delete: the record disappears from the app and from the tools but can be brought back with restore_record. type is one of care, harvest, sale, expense, observation, issue, note. Get ids from list_records, list_notes or get_tree. Deleting an issue hides it from the follow-up lists; its observations stay.')]
#[IsDestructive]
class DeleteRecord extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(RecordTypes::DELETABLE)->description('Kind of record to delete.')->required(),
            'id' => $schema->integer()->description('Id of the record to delete.')->required(),
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
            'id.required' => 'id is required; pass the id of the record to delete.',
        ]);

        $type = $validated['type'];
        $id = (int) $validated['id'];
        $record = RecordTypes::findWithTrashed($type, $id);

        if ($record === null) {
            throw $this->notFound($type, $id);
        }

        if ($record->getAttribute('deleted_at') !== null) {
            throw ValidationException::withMessages([
                'id' => ucfirst(RecordTypes::label($type))." {$id} is already deleted; bring it back with restore_record if that was a mistake.",
            ]);
        }

        $record->delete();

        return Response::json([
            'type' => $type,
            'id' => $id,
            'message' => 'Deleted. It can be restored with restore_record.',
        ]);
    }

    private function notFound(string $type, int $id): ValidationException
    {
        return ValidationException::withMessages([
            'id' => 'No '.RecordTypes::label($type)." with id {$id} exists. Check the id with ".($type === 'note' ? 'list_notes' : 'list_records').'.',
        ]);
    }
}
