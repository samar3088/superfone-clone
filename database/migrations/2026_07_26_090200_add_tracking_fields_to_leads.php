<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Lead-tracking fields the Create Contact form collects.
 |
 | source_type sits beside the existing free-text source: the type is the
 | channel it came through (Facebook, a CSV, a phone call), the source is the
 | particular campaign within it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_type', 60)->nullable()->after('source');
            // Money, so decimal rather than float — a rounding drift on a deal
            // value is the sort of thing nobody notices until a report is wrong.
            $table->decimal('deal_value', 12, 2)->nullable()->after('campaign');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'deal_value']);
        });
    }
};
