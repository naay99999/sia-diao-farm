<?php

namespace Database\Factories;

use App\Models\Finding;
use App\Models\Tree;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreeIssue>
 */
class TreeIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'finding_id' => Finding::factory()->problem(),
            'opened_on' => now()->toDateString(),
            'follow_up_on' => now()->addDays(TreeIssue::FOLLOW_UP_DAYS)->toDateString(),
            'resolved_on' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved_on' => now()->toDateString()]);
    }
}
