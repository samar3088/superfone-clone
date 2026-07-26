<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | First and last name, alongside the full name rather than instead of it.
 |
 | The app has stored one `name` everywhere, and the split only existed at the
 | edges — the client's import template has two columns, and some Facebook lead
 | forms send first_name and last_name separately. Both were being joined on the
 | way in and guessed at on the way out, so a name that does not split on the
 | last space ("Asha Devi Rao") came back wrong and nobody could correct it.
 |
 | Keeping `name` as well is deliberate. It is what every screen, export and
 | email already reads, and it is the only honest place to put a name that
 | arrived whole — Facebook's full_name, a walk-in typed in one box. The three
 | are kept in step by a saving hook on the model, so none can drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
        });

        $this->backfill();
    }

    /**
     * Split what is already on file, the same way the model will from now on:
     * on the last space, so a middle name stays with the first.
     */
    private function backfill(): void
    {
        DB::table('customers')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $name = trim((string) $row->name);
                    $cut = mb_strrpos($name, ' ');

                    DB::table('customers')->where('id', $row->id)->update([
                        'first_name' => $cut === false ? $name : mb_substr($name, 0, $cut),
                        'last_name' => $cut === false ? null : mb_substr($name, $cut + 1),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
