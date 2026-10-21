<?php

use App\Support\Provenance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tree_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_id')->constrained()->restrictOnDelete();
            $table->date('opened_on');
            $table->date('follow_up_on');
            $table->date('resolved_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
            Provenance::columns($table);

            $table->index(['resolved_on', 'follow_up_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tree_issues');
    }
};
