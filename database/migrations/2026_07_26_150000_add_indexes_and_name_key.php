<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Three indexes the screens had grown to need, and the key that makes finding
 | duplicate contacts a lookup rather than a scan.
 |
 | Every one of these was measured against what the pages actually query, not
 | added on the theory that more indexes are better. An index nobody reads is a
 | write slowed down for nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            /*
             | The contact's name reduced to something comparable: lower case,
             | punctuation gone, runs of spaces collapsed. "Asha Rao", "asha
             | rao" and "Asha  Rao." all key the same.
             |
             | Stored and indexed rather than computed per query, because the
             | duplicate finder groups on it. Computing it in SQL would mean
             | LOWER() over every row on every run — fine at 30 contacts, not at
             | the 12,500 waiting to be imported.
             */
            $table->string('name_key', 150)->nullable()->after('last_name');
            $table->index('name_key', 'customers_name_key_idx');

            // Offered by the table's sort control, and previously a filesort.
            $table->index('created_at', 'customers_created_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            /*
             | Every To-Dos load splits on type — Reminders is "type = REMINDER",
             | the other two are "type != REMINDER" — and then narrows to open
             | work. Both halves of that ran without an index.
             */
            $table->index(['type', 'completed_at'], 'tasks_type_idx');
        });

        $this->backfillNameKeys();
    }

    /**
     * Fill the key for contacts already on file.
     *
     * Chunked and written back one row at a time: the normalisation is PHP, so
     * it has to come back out to be computed. A one-off cost on a table that is
     * small today and will be filled by the importer afterwards, which keys
     * each row as it writes it.
     */
    private function backfillNameKeys(): void
    {
        DB::table('customers')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('customers')->where('id', $row->id)->update([
                        'name_key' => Customer::nameKey($row->name),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_name_key_idx');
            $table->dropIndex('customers_created_idx');
            $table->dropColumn('name_key');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_type_idx');
        });
    }
};
