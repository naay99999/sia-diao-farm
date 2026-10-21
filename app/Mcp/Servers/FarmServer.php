<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetFarmOverview;
use App\Mcp\Tools\GetTreatmentOutcomes;
use App\Mcp\Tools\GetTree;
use App\Mcp\Tools\ListRecords;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Sia Diao Farm')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
This server exposes the records of a durian farm (orchards, trees, care, harvests, sales, expenses, tree observations and follow-ups).

Start every conversation by calling `get_farm_overview`. It lists the orchards (with their ids), the issues that are due for a follow-up, all open issues, and the known findings. Use the ids it returns for later calls.

Knowledge sources, in this order of priority when you advise on a problem:
1. The farm's built-in safety rules and guidance from this system.
2. The farm's own recorded outcomes (what was applied to which tree, and whether the issue was resolved afterwards).
3. The farm's saved notes.
4. The web, only when the sources above do not answer the question.

Never invent pesticide or fertiliser rates, dilution ratios or pre-harvest intervals. If the sources above do not state them, say so and tell the user to check the product label or ask a local agricultural officer.

Writing data:
- Before calling any tool that creates, edits or deletes a record, restate a short summary of exactly what will be saved (orchard, tree codes, dates, amounts) and wait for the user's explicit confirmation in chat. A vague reply or earlier general instruction is not confirmation.
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
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
