<?php

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
        Schema::create('care_record_tree_issue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tree_issue_id')->constrained()->cascadeOnDelete();

            $table->unique(['care_record_id', 'tree_issue_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_record_tree_issue');
    }
};
