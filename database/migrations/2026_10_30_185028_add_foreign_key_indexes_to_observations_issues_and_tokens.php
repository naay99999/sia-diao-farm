<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys the 2026-10-26 indexes left uncovered. `tree_issues (tree_id, finding_id)` replaces the single
     * `tree_id` index: it serves the same lookups and also the open-issue check for a tree and finding.
     */
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->index('tree_issue_id');
            $table->index('finding_id');
        });

        Schema::table('tree_issues', function (Blueprint $table) {
            $table->index(['tree_id', 'finding_id']);
            $table->dropIndex(['tree_id']);
            $table->index('finding_id');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('tree_issues', function (Blueprint $table) {
            $table->dropIndex(['finding_id']);
            $table->index('tree_id');
            $table->dropIndex(['tree_id', 'finding_id']);
        });

        Schema::table('observations', function (Blueprint $table) {
            $table->dropIndex(['finding_id']);
            $table->dropIndex(['tree_issue_id']);
        });
    }
};
