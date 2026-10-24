<?php

use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Tree;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
    $this->tree = Tree::factory()->for($this->orchard)->create();
    $this->recordsUrl = route('records.index', ['type' => 'care', 'orchard_id' => $this->orchard->id]);
});

/**
 * @return array<string, array{0: Closure(): array{0: string, 1: string, 2: array<string, mixed>}}>
 */
dataset('recordTypes', [
    'care' => [function () {
        $record = CareRecord::factory()->for($this->orchard)->create();
        $record->trees()->sync([$this->tree->id]);

        return [route('care-records.update', $record), route('care-records.destroy', $record), [
            'type' => 'pesticide', 'performed_on' => '2026-06-01', 'tree_ids' => [$this->tree->id],
        ]];
    }],
    'observation' => [function () {
        $observation = Observation::factory()->for($this->tree)->create();

        return [route('observations.update', $observation), route('observations.destroy', $observation), [
            'observed_on' => '2026-06-01',
        ]];
    }],
    'harvest' => [function () {
        $harvest = Harvest::factory()->for($this->tree)->create();

        return [route('harvests.update', $harvest), route('harvests.destroy', $harvest), [
            'harvested_on' => '2026-06-01', 'weight_kg' => 9,
        ]];
    }],
    'sale' => [function () {
        $sale = Sale::factory()->for($this->orchard)->create();

        return [route('sales.update', $sale), route('sales.destroy', $sale), [
            'sold_on' => '2026-06-01', 'weight_kg' => 5, 'amount' => 500,
        ]];
    }],
]);

test('updating from the records page returns to it', function (Closure $make) {
    [$updateUrl, , $payload] = $make->call($this);

    $this->actingAs($this->user)
        ->from($this->recordsUrl)
        ->patch($updateUrl, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->recordsUrl);
})->with('recordTypes');

test('deleting from the records page returns to it', function (Closure $make) {
    [, $destroyUrl] = $make->call($this);

    $this->actingAs($this->user)
        ->from($this->recordsUrl)
        ->delete($destroyUrl)
        ->assertRedirect($this->recordsUrl);
})->with('recordTypes');

test('a foreign referer falls back to the usual page', function () {
    $sale = Sale::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->from('https://evil.example/records/sales?x=1')
        ->delete(route('sales.destroy', $sale))
        ->assertRedirect(route('orchards.show', $this->orchard));
});

test('a same-host referer outside the records pages falls back to the usual page', function () {
    $sale = Sale::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->from(route('trees.show', $this->tree))
        ->delete(route('sales.destroy', $sale))
        ->assertRedirect(route('orchards.show', $this->orchard));
});
