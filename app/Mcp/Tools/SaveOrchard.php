<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Farm\StoreOrchardRequest;
use App\Mcp\Support\RecordSerializer;
use App\Models\Orchard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_orchard')]
#[Description('Only call after the user confirmed a summary in chat. Creates an orchard, or replaces an existing one when id is given (then send every field again; omitted optional fields are cleared). Orchards cannot be deleted through these tools. Returns the saved orchard with its id.')]
class SaveOrchard extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing orchard to replace. Omit to create a new one.'),
            'name' => $schema->string()->description('Orchard name, for example สวนหน้าบ้าน.')->required(),
            'location' => $schema->string()->description('Where the orchard is.'),
            'notes' => $schema->string()->description('Free-form notes.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            ...(new StoreOrchardRequest)->rules(),
        ], [
            'name.required' => 'name is required; pass the orchard name.',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if (isset($validated['id'])) {
            $orchard = $this->existing(Orchard::query(), (int) $validated['id'], 'orchard', 'get_farm_overview');
            $orchard->update($attributes);
        } else {
            $orchard = Orchard::query()->create($attributes);
        }

        return Response::json(['orchard' => RecordSerializer::serialize($orchard)]);
    }
}
