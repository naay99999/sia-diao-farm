<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;

class Provenance
{
    /** Context key holding where the current write came from. */
    public const VIA_KEY = 'record_via';

    public const VIA_APP = 'app';

    public const VIA_MCP = 'mcp';

    /**
     * Add the who/where-from columns shared by every tracked table.
     */
    public static function columns(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->string('created_via', 10)->default(self::VIA_APP);
        $table->string('updated_via', 10)->default(self::VIA_APP);
    }

    /**
     * Drop the columns added by {@see self::columns()}.
     */
    public static function dropColumns(Blueprint $table): void
    {
        $table->dropConstrainedForeignId('created_by');
        $table->dropConstrainedForeignId('updated_by');
        $table->dropColumn(['created_via', 'updated_via']);
    }
}
