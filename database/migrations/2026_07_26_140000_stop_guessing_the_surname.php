<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Undo the guessed surnames.
 |
 | The previous migration split every stored name on its last space to fill the
 | new first/last columns. That was a guess dressed as a fact: "Asha Devi Rao"
 | has no knowable boundary, plenty of names have no surname, and once written
 | the guess is indistinguishable from a split somebody actually told us.
 |
 | So a whole name now goes wholly into the first name, and the last name is
 | filled only when a source states it — the two-column import template, a
 | Facebook form with separate fields, or a person typing into the two boxes.
 |
 | This puts the existing rows back in line with that rule. Safe to run in
 | full: nothing has reached production, so every split currently on file was
 | derived by that migration rather than stated by anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->whereNotNull('last_name')
            ->update([
                'first_name' => DB::raw('name'),
                'last_name' => null,
            ]);
    }

    /**
     * Deliberately nothing.
     *
     * Rolling back cannot recover a split that was never real, and re-deriving
     * it would reintroduce the guess this migration exists to remove.
     */
    public function down(): void {}
};
