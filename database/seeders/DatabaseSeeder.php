<?php

namespace Database\Seeders;

use App\Enums\CareType;
use App\Enums\ExpenseCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('farm.owner.email')],
            [
                'name' => 'เจ้าของสวน',
                'password' => config('farm.owner.password'),
            ],
        );

        if (app()->environment('local')) {
            $this->seedDemoOrchards();
        }
    }

    private function seedDemoOrchards(): void
    {
        foreach (['สวนบน', 'สวนล่าง'] as $orchardName) {
            $orchard = Orchard::factory()->create(['name' => $orchardName]);

            $trees = collect(range(1, 20))->map(fn (int $number) => Tree::factory()->create([
                'orchard_id' => $orchard->id,
                'code' => sprintf('%s-%02d', $orchardName === 'สวนบน' ? 'A' : 'B', $number),
                'variety' => 'หมอนทอง',
                'planted_on' => now()->subYears(5)->toDateString(),
            ]));

            $season = Season::factory()->create([
                'orchard_id' => $orchard->id,
                'name' => 'ฤดู '.(now()->year + 543),
                'started_on' => now()->startOfYear()->toDateString(),
                'ended_on' => null,
            ]);

            CareRecord::factory()->create([
                'orchard_id' => $orchard->id,
                'type' => CareType::Fertilizer,
                'performed_on' => now()->subDays(30)->toDateString(),
                'product' => 'ปุ๋ยสูตร 15-15-15',
            ])->trees()->attach($trees->pluck('id'));

            CareRecord::factory()->create([
                'orchard_id' => $orchard->id,
                'type' => CareType::Pesticide,
                'performed_on' => now()->subDays(10)->toDateString(),
            ])->trees()->attach($trees->take(5)->pluck('id'));

            foreach ($trees->take(5) as $tree) {
                Harvest::factory()->create([
                    'tree_id' => $tree->id,
                    'harvested_on' => now()->subDays(3)->toDateString(),
                ]);
            }

            Sale::factory()->create([
                'orchard_id' => $orchard->id,
                'sold_on' => now()->subDays(2)->toDateString(),
                'buyer_name' => 'พ่อค้าประจำ',
            ]);

            Expense::factory()->count(2)->create([
                'season_id' => $season->id,
                'category' => ExpenseCategory::cases()[0],
            ]);
        }
    }
}
