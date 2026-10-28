<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Farm\StoreSeasonRequest;
use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\Season;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_season')]
#[Description('Only call after the user confirmed a summary in chat. Opens a season (a harvest cycle) for an orchard, or replaces an existing season when id is given (then send every field again; omitted optional fields are cleared, and the season cannot be moved to another orchard). To close a season, send it again with ended_on. An orchard has at most one open season (no ended_on) and season dates never overlap. Pass orchard_id when the farm has more than one orchard. Returns the saved season with its id.')]
class SaveSeason extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing season to replace, from get_farm_overview. Omit to open a new one.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'name' => $schema->string()->description('Season name, for example ฤดู 2569.')->required(),
            'started_on' => $schema->string()->description('First day of the season, YYYY-MM-DD.')->required(),
            'ended_on' => $schema->string()->description('Last day of the season, YYYY-MM-DD, not before started_on. Omit while the season is still running.'),
            'notes' => $schema->string()->description('Free-form notes.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            'orchard_id' => ['nullable', 'integer'],
            ...(new StoreSeasonRequest)->rules(),
        ], [
            'name.required' => 'name is required; pass the season name.',
            'ended_on.after_or_equal' => 'ended_on must not be before started_on.',
            ...$this->dateMessages('started_on', 'ended_on'),
        ]);

        $season = isset($validated['id'])
            ? $this->existing(Season::query(), (int) $validated['id'], 'season', 'get_farm_overview')
            : null;

        if ($season !== null && isset($validated['orchard_id']) && (int) $validated['orchard_id'] !== $season->orchard_id) {
            throw ValidationException::withMessages([
                'orchard_id' => "Season {$season->id} belongs to orchard {$season->orchard_id} and cannot be moved to another orchard; omit orchard_id.",
            ]);
        }

        $orchardId = $season->orchard_id ?? TreeLookup::orchard($validated['orchard_id'] ?? null)->id;
        $attributes = [
            'name' => $validated['name'],
            'started_on' => Date::parse($validated['started_on'])->toDateString(),
            'ended_on' => isset($validated['ended_on']) ? Date::parse($validated['ended_on'])->toDateString() : null,
            'notes' => $validated['notes'] ?? null,
        ];

        $conflict = Season::conflictWith($orchardId, $attributes['started_on'], $attributes['ended_on'], $season?->id);

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'started_on' => match ($conflict) {
                    Season::CONFLICT_OPEN => 'The orchard already has an open season; close it first by sending it again with ended_on.',
                    Season::CONFLICT_OVERLAP => 'These dates overlap another season of the orchard; check the seasons in get_farm_overview.',
                },
            ]);
        }

        if ($season !== null) {
            $season->update($attributes);
        } else {
            $season = Season::query()->create(['orchard_id' => $orchardId, ...$attributes]);
        }

        return Response::json(['season' => RecordSerializer::serialize($season)]);
    }
}
