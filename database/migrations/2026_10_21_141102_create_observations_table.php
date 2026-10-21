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
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_id')->constrained()->restrictOnDelete();
            $table->foreignId('tree_issue_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->string('severity')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            Provenance::columns($table);

            $table->index(['tree_id', 'observed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
