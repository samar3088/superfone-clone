<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Which organisation a member belongs to.
 |
 | One team today, so every existing member joins it. The column earns its keep
 | when a second virtual number arrives and staff are split between them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // nullOnDelete rather than cascade: removing a team must not delete
            // the people in it.
            $table->foreignId('team_id')->nullable()->after('id')
                ->constrained('teams')->nullOnDelete();
        });

        if ($teamId = Team::query()->orderBy('id')->value('id')) {
            DB::table('users')->whereNull('team_id')->update(['team_id' => $teamId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
