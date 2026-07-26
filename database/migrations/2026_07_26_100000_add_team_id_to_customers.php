<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Which organisation a contact belongs to.
 |
 | One organisation today, so every existing contact joins it. The column earns
 | its keep when a second virtual number arrives and each carries its own book
 | of contacts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // nullOnDelete, matching users: removing an organisation must not
            // delete the people in it.
            $table->foreignId('team_id')->nullable()->after('id')
                ->constrained('teams')->nullOnDelete();
        });

        if ($teamId = Team::query()->orderBy('id')->value('id')) {
            DB::table('customers')->whereNull('team_id')->update(['team_id' => $teamId]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
