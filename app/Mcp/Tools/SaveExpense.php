<?php

namespace App\Mcp\Tools;

use App\Enums\ExpenseCategory;
use App\Http\Requests\Farm\StoreExpenseRequest;
use App\Mcp\Support\RecordSerializer;
use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_expense')]
#[Description('Only call after the user confirmed a summary in chat. Records a farm expense, or replaces an existing expense when id is given (then send every field again; omitted optional fields are cleared). Give season_id (from get_farm_overview) to attach the expense to a season; leave it out for a general expense. Returns the saved expense with its id.')]
class SaveExpense extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing expense to replace. Omit to create a new one.'),
            'season_id' => $schema->integer()->description('Season the expense belongs to. Omit for a general expense.'),
            'category' => $schema->string()->enum(array_column(ExpenseCategory::cases(), 'value'))->description('Expense category.')->required(),
            'spent_on' => $schema->string()->description('Date the money was spent, YYYY-MM-DD.')->required(),
            'amount' => $schema->number()->min(0)->description('Amount in baht, greater than 0.')->required(),
            'notes' => $schema->string()->description('Free-form notes.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $categories = implode(', ', array_column(ExpenseCategory::cases(), 'value'));

        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            ...(new StoreExpenseRequest)->rules(),
        ], [
            'season_id.exists' => 'season_id :input does not exist; use a season id from get_farm_overview, or omit it.',
            'category.required' => "category is required; use one of: {$categories}.",
            'category.enum' => "category must be one of: {$categories}.",
            'amount.required' => 'amount is required; pass the amount in baht.',
            'amount.gt' => 'amount must be greater than 0.',
            ...$this->dateMessages('spent_on'),
        ]);

        $attributes = [
            'season_id' => $validated['season_id'] ?? null,
            'category' => $validated['category'],
            'spent_on' => $validated['spent_on'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ];

        if (isset($validated['id'])) {
            $expense = $this->existing(Expense::query(), (int) $validated['id'], 'expense');
            $expense->update($attributes);
        } else {
            $expense = Expense::query()->create($attributes);
        }

        return Response::json(['expense' => RecordSerializer::serialize($expense)]);
    }
}
