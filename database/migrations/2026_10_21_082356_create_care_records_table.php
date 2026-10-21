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
        Schema::create('care_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orchard_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('performed_on');
            $table->string('product')->nullable();
            $table->string('quantity')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_records');
    }
};
