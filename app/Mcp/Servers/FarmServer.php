<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddTrees;
use App\Mcp\Tools\DeleteRecord;
use App\Mcp\Tools\GetFarmOverview;
use App\Mcp\Tools\GetSeasonSummary;
use App\Mcp\Tools\GetTreatmentOutcomes;
use App\Mcp\Tools\GetTree;
use App\Mcp\Tools\ListNotes;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\RecordFollowUp;
use App\Mcp\Tools\RecordObservation;
use App\Mcp\Tools\RestoreRecord;
use App\Mcp\Tools\SaveCareRecord;
use App\Mcp\Tools\SaveExpense;
use App\Mcp\Tools\SaveHarvest;
use App\Mcp\Tools\SaveHarvests;
use App\Mcp\Tools\SaveNote;
use App\Mcp\Tools\SaveOrchard;
use App\Mcp\Tools\SaveSale;
use App\Mcp\Tools\SaveSeason;
use App\Mcp\Tools\SetTreeRemoved;
use App\Mcp\Tools\UpdateTree;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Sia Diao Farm')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
This server exposes the records of a durian farm (orchards, seasons, trees, care, harvests, sales, expenses, tree observations and follow-ups).

Start every conversation by calling `get_farm_overview`. It lists the orchards and their seasons (with their ids), the issues that are due for a follow-up, all open issues, and the known findings. Use the ids it returns for later calls.

Knowledge sources, in this order of priority when you advise on a problem:
1. The farm's built-in safety rules and guidance from this system.
2. The farm's own recorded outcomes (what was applied to which tree, and whether the issue was resolved afterwards).
3. The farm's saved notes (`list_notes`, filtered by the finding and/or tree you are advising on; general notes are included).
4. The web, only when the sources above do not answer the question.

Never invent pesticide or fertiliser rates, dilution ratios or pre-harvest intervals. If the sources above do not state them, say so and tell the user to check the product label or ask a local agricultural officer.

Writing data:
- Before calling any tool that creates, edits or deletes a record, restate a short summary of exactly what will be saved (orchard, tree codes, dates, amounts) and wait for the user's explicit confirmation in chat. A vague reply or earlier general instruction is not confirmation.
- Save a note with `save_note` only when the user asks to remember something. Deleting uses `delete_record` and is a soft delete; `restore_record` undoes it.
- When the farm has no orchards or trees yet, set them up with `save_orchard`, `save_season` and `add_trees`. Orchards, seasons and trees cannot be deleted through these tools; mark a tree that is gone with `set_tree_removed`.
- Do not repeat a write call after a failure without telling the user what failed.
- Refer to trees by their tree code (for example A-001), never by database id. Pass `orchard_id` whenever the farm has more than one orchard.

Language: reply to the user in Thai, even though these instructions and the tool schemas are in English. Keep farm terms the way the user writes them.
TEXT)]
class FarmServer extends Server
{
    protected array $tools = [
        GetFarmOverview::class,
        GetTree::class,
        ListRecords::class,
        GetTreatmentOutcomes::class,
        GetSeasonSummary::class,
        SaveOrchard::class,
        SaveSeason::class,
        AddTrees::class,
        UpdateTree::class,
        SetTreeRemoved::class,
        SaveCareRecord::class,
        RecordObservation::class,
        RecordFollowUp::class,
        SaveHarvest::class,
        SaveHarvests::class,
        SaveSale::class,
        SaveExpense::class,
        SaveNote::class,
        ListNotes::class,
        DeleteRecord::class,
        RestoreRecord::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
