<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->string('description')->nullable();
            $table->foreignId('crop_cycle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
