<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained();
            $table->decimal('weight_kg', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_items');
    }
};
