<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruit_varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fruit_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('days_to_harvest');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruit_varieties');
    }
};
