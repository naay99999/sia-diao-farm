<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fruit_variety_id')->constrained();
            $table->string('label');
            $table->string('stage')->default('soil_prep');
            $table->string('status')->default('active');
            $table->date('flowering_date')->nullable();
            $table->date('expected_harvest_date')->nullable();
            $table->date('started_at');
            $table->date('closed_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};
