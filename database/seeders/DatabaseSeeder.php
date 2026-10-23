<?php

namespace Database\Seeders;

use App\Enums\CareType;
use App\Enums\ExpenseCategory;
use App\Enums\FindingKind;
use App\Enums\FollowUpOutcome;
use App\Enums\Severity;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
use App\Models\Finding;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedOwner();

        if (app()->environment('local')) {
            $this->seedDemoOrchards();
            $this->seedGeneralNotes();
            $this->seedAiRecords();
        }
    }

    /**
     * Create the owner only when none exists, so repeated deploys never add another or reclaim one.
     */
    private function seedOwner(): void
    {
        if (User::query()->where('is_owner', true)->exists()) {
            return;
        }

        $ownerPassword = config('farm.owner.password');

        if (! app()->environment(['local', 'testing']) && ($ownerPassword === null || $ownerPassword === '' || $ownerPassword === 'password')) {
            throw new RuntimeException('Set OWNER_PASSWORD to a non-default value before seeding outside local/testing environments.');
        }

        $owner = User::firstOrCreate(
            ['email' => config('farm.owner.email')],
            [
                'name' => 'เจ้าของสวน',
                'password' => $ownerPassword,
            ],
        );

        $owner->forceFill(['is_owner' => true])->save();
    }

    /**
     * A few records written as if Claude saved them through MCP, so the AI badge and feed have something to show.
     */
    private function seedAiRecords(): void
    {
        Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);

        $tree = Tree::query()->where('code', 'A-05')->firstOrFail();

        Harvest::factory()->create([
            'tree_id' => $tree->id,
            'harvested_on' => now()->subDay()->toDateString(),
            'weight_kg' => 18.4,
            'fruit_count' => 6,
            'notes' => 'บันทึกผ่าน Claude',
        ]);

        Observation::factory()->create([
            'tree_id' => Tree::query()->where('code', 'B-03')->value('id'),
            'finding_id' => Finding::resolveByName('ดอกบาน', FindingKind::Stage)->id,
            'observed_on' => now()->toDateString(),
        ]);

        FarmNote::factory()->create([
            'tree_id' => $tree->id,
            'body' => 'ต้นนี้ติดผลดกทุกปี ควรค้ำกิ่งก่อนผลโต',
        ]);

        Context::forget(Provenance::VIA_KEY);
    }

    private function seedDemoOrchards(): void
    {
        $orchards = [
            ['name' => 'สวนบน', 'prefix' => 'A', 'location' => 'เนินเขาหลังบ้าน'],
            ['name' => 'สวนล่าง', 'prefix' => 'B', 'location' => 'ริมลำห้วย'],
        ];

        foreach ($orchards as $orchardData) {
            $orchard = Orchard::factory()->create([
                'name' => $orchardData['name'],
                'location' => $orchardData['location'],
            ]);

            $trees = collect(range(1, 20))->map(fn (int $number) => Tree::factory()->create([
                'orchard_id' => $orchard->id,
                'code' => sprintf('%s-%02d', $orchardData['prefix'], $number),
                'variety' => $number % 4 === 0 ? 'ก้านยาว' : 'หมอนทอง',
                'planted_on' => now()->subYears($number % 3 === 0 ? 8 : 5)->toDateString(),
                'removed_on' => $number === 20 ? now()->subMonths(4)->toDateString() : null,
                'notes' => $number === 20 ? 'ยืนต้นตายจากโรครากเน่า โค่นทิ้งแล้ว' : null,
            ]));

            $activeTrees = $trees->filter(fn (Tree $tree) => $tree->removed_on === null)->values();

            $this->seedPastSeason($orchard, $activeTrees);
            $this->seedCurrentSeason($orchard, $activeTrees);
            $this->seedObservationsAndIssues($orchard, $activeTrees);
        }
    }

    /**
     * @param  Collection<int, Tree>  $trees
     */
    private function seedPastSeason(Orchard $orchard, Collection $trees): void
    {
        $lastYear = now()->subYear();

        $season = Season::factory()->create([
            'orchard_id' => $orchard->id,
            'name' => 'ฤดู '.($lastYear->year + 543),
            'started_on' => $lastYear->copy()->startOfYear()->toDateString(),
            'ended_on' => $lastYear->copy()->endOfYear()->toDateString(),
            'notes' => 'ปีนี้ฝนมาเร็ว ผลผลิตดีกว่าที่คาด',
        ]);

        foreach ($trees as $tree) {
            Harvest::factory()->create([
                'tree_id' => $tree->id,
                'harvested_on' => $lastYear->copy()->setMonth(6)->setDay(fake()->numberBetween(1, 28))->toDateString(),
                'fruit_count' => fake()->numberBetween(10, 40),
            ]);
        }

        foreach ([6, 7] as $month) {
            Sale::factory()->create([
                'orchard_id' => $orchard->id,
                'sold_on' => $lastYear->copy()->setMonth($month)->setDay(15)->toDateString(),
                'buyer_name' => fake()->randomElement(['ล้งจันทบุรี', 'พ่อค้าประจำ']),
                'weight_kg' => fake()->randomFloat(2, 400, 1200),
                'amount' => fake()->randomFloat(2, 60000, 150000),
            ]);
        }

        foreach (ExpenseCategory::cases() as $category) {
            Expense::factory()->create([
                'season_id' => $season->id,
                'category' => $category,
                'spent_on' => $lastYear->copy()->setMonth(fake()->numberBetween(1, 11))->setDay(10)->toDateString(),
            ]);
        }
    }

    /**
     * @param  Collection<int, Tree>  $trees
     */
    private function seedCurrentSeason(Orchard $orchard, Collection $trees): void
    {
        $season = Season::factory()->create([
            'orchard_id' => $orchard->id,
            'name' => 'ฤดู '.(now()->year + 543),
            'started_on' => now()->startOfYear()->toDateString(),
            'ended_on' => null,
        ]);

        CareRecord::factory()->create([
            'orchard_id' => $orchard->id,
            'type' => CareType::Fertilizer,
            'performed_on' => now()->subDays(60)->toDateString(),
            'product' => 'ปุ๋ยคอก',
            'quantity' => '5 กก./ต้น',
        ])->trees()->attach($trees->pluck('id'));

        CareRecord::factory()->create([
            'orchard_id' => $orchard->id,
            'type' => CareType::Fertilizer,
            'performed_on' => now()->subDays(30)->toDateString(),
            'product' => 'ปุ๋ยสูตร 15-15-15',
            'quantity' => '1 กก./ต้น',
        ])->trees()->attach($trees->pluck('id'));

        CareRecord::factory()->create([
            'orchard_id' => $orchard->id,
            'type' => CareType::Other,
            'performed_on' => now()->subDays(20)->toDateString(),
            'notes' => 'ตัดแต่งกิ่งและค้ำกิ่ง',
        ])->trees()->attach($trees->take(8)->pluck('id'));

        foreach ($trees->take(10) as $index => $tree) {
            Harvest::factory()->create([
                'tree_id' => $tree->id,
                'harvested_on' => now()->subDays($index < 5 ? 3 : 12)->toDateString(),
                'fruit_count' => fake()->numberBetween(8, 30),
            ]);
        }

        Sale::factory()->create([
            'orchard_id' => $orchard->id,
            'sold_on' => now()->subDays(10)->toDateString(),
            'buyer_name' => 'ล้งจันทบุรี',
            'notes' => 'เกรด A ส่งออก',
        ]);

        Sale::factory()->create([
            'orchard_id' => $orchard->id,
            'sold_on' => now()->subDays(2)->toDateString(),
            'buyer_name' => 'พ่อค้าประจำ',
        ]);

        $currentExpenses = [
            [ExpenseCategory::Fertilizer, 60, 'ปุ๋ยคอก 40 กระสอบ'],
            [ExpenseCategory::Fertilizer, 30, 'ปุ๋ยสูตร 15-15-15'],
            [ExpenseCategory::Pesticide, 8, 'ยาฆ่าไร'],
            [ExpenseCategory::Labor, 20, 'ค่าแรงตัดแต่งกิ่ง'],
            [ExpenseCategory::Labor, 3, 'ค่าแรงเก็บเกี่ยว'],
            [ExpenseCategory::Utilities, 15, 'ค่าไฟปั๊มน้ำ'],
            [ExpenseCategory::Equipment, 45, 'สายยางและหัวสปริงเกอร์'],
        ];

        foreach ($currentExpenses as [$category, $daysAgo, $notes]) {
            Expense::factory()->create([
                'season_id' => $season->id,
                'category' => $category,
                'spent_on' => now()->subDays($daysAgo)->toDateString(),
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Growth-stage sightings plus issues in every state: due, upcoming, and resolved.
     *
     * @param  Collection<int, Tree>  $trees
     */
    private function seedObservationsAndIssues(Orchard $orchard, Collection $trees): void
    {
        $flowering = Finding::resolveByName('ออกดอก', FindingKind::Stage);
        $fruitSet = Finding::resolveByName('ติดผล', FindingKind::Stage);

        foreach ($trees->slice(10, 6) as $tree) {
            Observation::factory()->create([
                'tree_id' => $tree->id,
                'finding_id' => $flowering->id,
                'observed_on' => now()->subDays(25)->toDateString(),
            ]);

            Observation::factory()->create([
                'tree_id' => $tree->id,
                'finding_id' => $fruitSet->id,
                'observed_on' => now()->subDays(5)->toDateString(),
                'notes' => 'ติดผลดี ควรโยงกิ่ง',
            ]);
        }

        $dueIssue = $this->openIssue($trees[2], 'ไรแดง', Severity::Medium, openedDaysAgo: 10, followUpDaysAgo: 3, notes: 'ใบด้านล่างซีด มีจุดเหลือง');
        $this->openIssue($trees[6], 'เพลี้ยไฟ', Severity::Low, openedDaysAgo: 2, followUpDaysAgo: -5, notes: 'พบที่ยอดอ่อนบางส่วน');
        $overdueIssue = $this->openIssue($trees[9], 'ไฟทอปธอร่า/รากเน่าโคนเน่า', Severity::High, openedDaysAgo: 21, followUpDaysAgo: 7, notes: 'มีน้ำยางไหลที่โคนต้น');

        Observation::factory()->followUp(FollowUpOutcome::NotResolved)->create([
            'tree_id' => $overdueIssue->tree_id,
            'finding_id' => $overdueIssue->finding_id,
            'tree_issue_id' => $overdueIssue->id,
            'observed_on' => now()->subDays(14)->toDateString(),
            'severity' => Severity::High,
            'notes' => 'แผลยังไม่แห้ง ทาสารซ้ำ',
        ]);

        $resolvedIssue = $this->openIssue($trees[11], 'ใบไหม้', Severity::Medium, openedDaysAgo: 40, followUpDaysAgo: 26, notes: 'ใบไหม้ขอบใบ ด้านทิศตะวันตก');

        Observation::factory()->followUp(FollowUpOutcome::Resolved)->create([
            'tree_id' => $resolvedIssue->tree_id,
            'finding_id' => $resolvedIssue->finding_id,
            'tree_issue_id' => $resolvedIssue->id,
            'observed_on' => now()->subDays(26)->toDateString(),
            'notes' => 'แตกใบใหม่ปกติแล้ว',
        ]);

        $resolvedIssue->update(['resolved_on' => now()->subDays(26)->toDateString()]);

        $treatment = CareRecord::factory()->create([
            'orchard_id' => $orchard->id,
            'type' => CareType::Pesticide,
            'performed_on' => now()->subDays(9)->toDateString(),
            'product' => 'อะบาเมกติน',
            'quantity' => '20 มล./น้ำ 20 ลิตร',
        ]);
        $treatment->trees()->attach([$dueIssue->tree_id]);
        $treatment->issues()->attach([$dueIssue->id]);

        $fungicide = CareRecord::factory()->create([
            'orchard_id' => $orchard->id,
            'type' => CareType::Pesticide,
            'performed_on' => now()->subDays(20)->toDateString(),
            'product' => 'ฟอสอีทิล-อะลูมิเนียม',
            'notes' => 'ทาแผลที่โคนต้น',
        ]);
        $fungicide->trees()->attach([$overdueIssue->tree_id]);
        $fungicide->issues()->attach([$overdueIssue->id]);

        FarmNote::factory()->create([
            'tree_id' => $trees[9]->id,
            'body' => 'ต้นนี้อยู่จุดน้ำขัง ควรขุดร่องระบายน้ำก่อนหน้าฝน',
        ]);
    }

    /**
     * Open an issue on a tree together with the sighting that started it.
     */
    private function openIssue(Tree $tree, string $findingName, Severity $severity, int $openedDaysAgo, int $followUpDaysAgo, string $notes): TreeIssue
    {
        $finding = Finding::resolveByName($findingName, FindingKind::Problem);

        $issue = TreeIssue::factory()->create([
            'tree_id' => $tree->id,
            'finding_id' => $finding->id,
            'opened_on' => now()->subDays($openedDaysAgo)->toDateString(),
            'follow_up_on' => now()->subDays($followUpDaysAgo)->toDateString(),
        ]);

        Observation::factory()->create([
            'tree_id' => $tree->id,
            'finding_id' => $finding->id,
            'tree_issue_id' => $issue->id,
            'observed_on' => $issue->opened_on->toDateString(),
            'severity' => $severity,
            'notes' => $notes,
        ]);

        return $issue;
    }

    private function seedGeneralNotes(): void
    {
        $notes = [
            ['ไรแดง', 'ไรแดงระบาดหนักช่วงแล้ง ฉีดน้ำใต้ใบช่วยลดได้ ถ้าไม่หายค่อยใช้อะบาเมกติน'],
            ['เพลี้ยไฟ', 'เพลี้ยไฟมักมาช่วงแตกใบอ่อน ให้สังเกตยอดอ่อนทุก 3-4 วัน'],
            ['ไฟทอปธอร่า/รากเน่าโคนเน่า', 'ถ้าเจอน้ำยางไหลที่โคน ให้ถากแผลแล้วทาฟอสอีทิล-อะลูมิเนียม และอย่าให้น้ำขังโคนต้น'],
            ['ออกดอก', 'ช่วงออกดอกงดให้ปุ๋ยไนโตรเจนสูง และลดการให้น้ำลงเล็กน้อย'],
            [null, 'ปุ๋ยคอกสั่งจากร้านลุงสมได้ราคาถูกกว่าในเมือง'],
            [null, 'ปั๊มน้ำสวนล่างควรล้างกรองทุกเดือน'],
        ];

        foreach ($notes as [$findingName, $body]) {
            FarmNote::factory()->create([
                'finding_id' => $findingName === null ? null : Finding::query()->where('name', $findingName)->value('id'),
                'body' => $body,
            ]);
        }
    }
}
