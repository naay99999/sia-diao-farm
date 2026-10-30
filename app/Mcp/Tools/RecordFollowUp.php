<?php

namespace App\Mcp\Tools;

use App\Actions\Farm\RecordFollowUp as RecordFollowUpAction;
use App\Enums\FollowUpOutcome;
use App\Mcp\Support\RecordSerializer;
use App\Models\TreeIssue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('record_follow_up')]
#[Description('Only call after the user confirmed a summary in chat. Records a follow-up check of an open tree issue. outcome "resolved" closes the issue; "not_resolved" keeps it open and schedules the next check (next_follow_up_on, default 7 days after observed_on). Closed issues and issues of removed trees are rejected. Returns the saved observation and the updated issue.')]
class RecordFollowUp extends FarmWriteTool
{
    public function __construct(private RecordFollowUpAction $recordFollowUp) {}

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->integer()->description('Id of the open issue, from get_farm_overview or get_tree.')->required(),
            'observed_on' => $schema->string()->description('Date of the check, YYYY-MM-DD.')->required(),
            'outcome' => $schema->string()->enum(array_column(FollowUpOutcome::cases(), 'value'))->description('"resolved" if the problem is gone, "not_resolved" if it is still there.')->required(),
            'notes' => $schema->string()->description('Free-form notes.'),
            'next_follow_up_on' => $schema->string()->description('When to check again if not resolved, YYYY-MM-DD, not before observed_on. Default is 7 days after observed_on.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $outcomes = implode(', ', array_column(FollowUpOutcome::cases(), 'value'));

        $validated = $this->validated($request, [
            'issue_id' => ['required', 'integer'],
            'observed_on' => ['required', 'date'],
            'outcome' => ['required', Rule::enum(FollowUpOutcome::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_on' => ['nullable', 'date', 'after_or_equal:observed_on'],
        ], [
            'issue_id.required' => 'issue_id is required; use an issue id from get_farm_overview or get_tree.',
            'outcome.required' => "outcome is required; use one of: {$outcomes}.",
            'outcome.enum' => "outcome must be one of: {$outcomes}.",
            'next_follow_up_on.date' => 'next_follow_up_on must be a date such as 2026-10-21.',
            'next_follow_up_on.after_or_equal' => 'next_follow_up_on must not be earlier than observed_on.',
            ...$this->dateMessages('observed_on'),
        ]);

        $issue = TreeIssue::query()->find((int) $validated['issue_id']) ?? throw ValidationException::withMessages([
            'issue_id' => "issue_id {$validated['issue_id']} does not exist; use an issue id from get_farm_overview or get_tree.",
        ]);

        $observation = $this->recordFollowUp->handle($issue, [
            'observed_on' => $validated['observed_on'],
            'outcome' => $validated['outcome'],
            'notes' => $validated['notes'] ?? null,
            'next_follow_up_on' => $validated['next_follow_up_on'] ?? null,
        ]);

        return Response::json([
            'observation' => RecordSerializer::serialize($observation),
            'issue' => RecordSerializer::serialize($issue->refresh()),
        ]);
    }
}
