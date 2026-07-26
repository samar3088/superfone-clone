<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Who added this contact by hand.
 |
 | Left null for anyone the Facebook sync created — nobody added them, they
 | arrived. That distinction is the point: "Created by" answers who typed
 | someone in, not who happens to work them now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('team_id')
                ->constrained('users')->nullOnDelete();

            $table->index(['created_by', 'created_at'], 'customers_creator_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_creator_idx');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
