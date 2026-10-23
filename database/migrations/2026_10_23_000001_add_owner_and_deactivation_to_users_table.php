<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('password');
            $table->timestamp('deactivated_at')->nullable()->after('is_owner');
        });

        $ownerId = DB::table('users')->where('email', config('farm.owner.email'))->value('id')
            ?? DB::table('users')->min('id');

        if ($ownerId !== null) {
            DB::table('users')->where('id', $ownerId)->update(['is_owner' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_owner', 'deactivated_at']);
        });
    }
};
