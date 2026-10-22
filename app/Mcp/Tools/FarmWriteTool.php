<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TreeLookup;
use App\Models\CareRecord;
use App\Models\Orchard;
use App\Models\Sale;
use App\Support\Provenance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class of every tool that writes farm data.
 *
 * Whatever the transport (HTTP route middleware, a test harness), every write runs inside a Context
 * scope that marks it as coming from MCP, so the provenance columns are always filled with `mcp`.
 * The scope is restored afterwards so nothing leaks into later work in the same process.
 */
abstract class FarmWriteTool extends Tool
{
    final public function handle(Request $request): Response
    {
        return Context::scope(
            fn (): Response => $this->save($request),
            [Provenance::VIA_KEY => Provenance::VIA_MCP],
        );
    }

    abstract protected function save(Request $request): Response;

    /**
     * Validate the tool arguments with English messages, because the AI client reads them, not the farmer.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validated(Request $request, array $rules, array $messages = []): array
    {
        $locale = App::getLocale();
        App::setLocale('en');

        try {
            return $request->validate($rules, $messages);
        } finally {
            App::setLocale($locale);
        }
    }

    /**
     * Required/date messages that tell the client how to fix the argument.
     *
     * @return array<string, string>
     */
    protected function dateMessages(string ...$fields): array
    {
        $messages = [];

        foreach ($fields as $field) {
            $messages["{$field}.required"] = "{$field} is required; use a date such as 2026-10-21.";
            $messages["{$field}.date"] = "{$field} must be a date such as 2026-10-21.";
        }

        return $messages;
    }

    /**
     * Find a live record to update, or explain how to continue.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     *
     * @throws ValidationException
     */
    protected function existing(Builder $query, int $id, string $label, string $listTool = 'list_records'): Model
    {
        return $query->find($id) ?? throw ValidationException::withMessages([
            'id' => "No {$label} with id {$id} exists. Check the id with {$listTool}; if it was deleted, bring it back with restore_record first.",
        ]);
    }

    /**
     * The orchard a record belongs to. A new record uses the given orchard (or the farm's only one); an
     * existing record keeps its orchard, and naming a different one is an error.
     *
     * @throws ValidationException
     */
    protected function orchardFor(CareRecord|Sale|null $record, ?int $orchardId, string $label): Orchard
    {
        if ($record === null) {
            return TreeLookup::orchard($orchardId);
        }

        if ($orchardId !== null && $orchardId !== $record->orchard_id) {
            throw ValidationException::withMessages([
                'orchard_id' => "{$label} {$record->id} belongs to orchard {$record->orchard_id} and cannot be moved to another orchard; omit orchard_id, or delete the record and create a new one.",
            ]);
        }

        return $record->orchard;
    }
}
