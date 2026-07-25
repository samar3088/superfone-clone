<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | When the assignee should be told about this lead, and whether they have been.
 |
 | The integration's rules can ask for a delay — "tell me thirty minutes after
 | it lands" — so the mail is scheduled here and sent by a scheduled command,
 | rather than fired the instant the lead is written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('notify_at')->nullable()->after('viewed_at');
            $table->timestamp('notified_at')->nullable()->after('notify_at');

            // The sweep: anything due and not yet sent.
            $table->index(['notified_at', 'notify_at'], 'leads_notify_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_notify_idx');
            $table->dropColumn(['notify_at', 'notified_at']);
        });
    }
};
