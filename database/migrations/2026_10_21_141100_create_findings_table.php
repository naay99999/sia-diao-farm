<?php

use App\Enums\FindingKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $problems = [
        'ไรแดง',
        'เพลี้ยไฟ',
        'เพลี้ยไก่แจ้ง',
        'หนอนเจาะผล',
        'ใบไหม้',
        'ไฟทอปธอร่า/รากเน่าโคนเน่า',
        'ราสีชมพู',
        'ใบติด',
    ];

    /**
     * @var array<int, string>
     */
    private array $stages = [
        'แตกใบอ่อน',
        'ออกดอก',
        'ดอกบาน',
        'ติดผล',
        'ผลดก',
        'ผลร่วง',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind');
            $table->timestamps();
        });

        $now = now();
        $rows = [];

        foreach ($this->problems as $name) {
            $rows[] = ['name' => $name, 'kind' => FindingKind::Problem->value, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach ($this->stages as $name) {
            $rows[] = ['name' => $name, 'kind' => FindingKind::Stage->value, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('findings')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
