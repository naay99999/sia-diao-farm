<?php

namespace Database\Factories;

use App\Enums\FollowUpOutcome;
use App\Enums\Severity;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'finding_id' => Finding::factory(),
            'tree_issue_id' => null,
            'observed_on' => now()->toDateString(),
            'severity' => null,
            'outcome' => null,
            'notes' => null,
        ];
    }

    public function problem(): static
    {
        return $this->state(fn () => ['finding_id' => Finding::factory()->problem()]);
    }

    public function stage(): static
    {
        return $this->state(fn () => ['finding_id' => Finding::factory()->stage()]);
    }

    public function severity(Severity $severity): static
    {
        return $this->state(fn () => ['severity' => $severity]);
    }

    public function followUp(FollowUpOutcome $outcome = FollowUpOutcome::NotResolved): static
    {
        return $this->state(fn () => ['outcome' => $outcome]);
    }
}
