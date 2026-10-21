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
        Schema::create('trees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orchard_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('variety')->nullable();
            $table->date('planted_on')->nullable();
            $table->date('removed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['orchard_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trees');
    }
};
