<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Farm\StoreSaleRequest;
use App\Mcp\Support\RecordSerializer;
use App\Models\Sale;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_sale')]
#[Description('Only call after the user confirmed a summary in chat. Records a sale of durians from an orchard, or replaces an existing sale when id is given (then send every field again; omitted optional fields are cleared, and the sale cannot be moved to another orchard). Pass orchard_id when the farm has more than one orchard. Returns the saved sale with its id.')]
class SaveSale extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing sale to replace. Omit to create a new one.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'sold_on' => $schema->string()->description('Sale date, YYYY-MM-DD.')->required(),
            'buyer_name' => $schema->string()->description('Who bought the fruit.'),
            'weight_kg' => $schema->number()->min(0)->description('Total weight sold in kilograms, greater than 0.')->required(),
            'amount' => $schema->number()->min(0)->description('Total amount received in baht, greater than 0.')->required(),
            'notes' => $schema->string()->description('Free-form notes.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            'orchard_id' => ['nullable', 'integer'],
            ...(new StoreSaleRequest)->rules(),
        ], [
            'weight_kg.required' => 'weight_kg is required; pass the weight in kilograms.',
            'weight_kg.gt' => 'weight_kg must be greater than 0.',
            'amount.required' => 'amount is required; pass the total in baht.',
            'amount.gt' => 'amount must be greater than 0.',
            ...$this->dateMessages('sold_on'),
        ]);

        $sale = isset($validated['id'])
            ? $this->existing(Sale::query(), (int) $validated['id'], 'sale')
            : null;
        $orchard = $this->orchardFor($sale, $validated['orchard_id'] ?? null, 'Sale');

        $attributes = [
            'sold_on' => $validated['sold_on'],
            'buyer_name' => $validated['buyer_name'] ?? null,
            'weight_kg' => $validated['weight_kg'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($sale === null) {
            $sale = $orchard->sales()->create($attributes);
        } else {
            $sale->update($attributes);
        }

        return Response::json(['sale' => RecordSerializer::serialize($sale)]);
    }
}
