<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Marks a lead as a repeat enquiry from someone already in the same campaign.
 |
 | Distinct from the unique external_id, which stops the *same* Facebook
 | submission being imported twice. This is about a person submitting the same
 | form a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('is_existing')->default(false)->after('viewed_at');

            // Which earlier lead this repeats, so the screen can point at it
            // rather than just saying "seen before".
            $table->foreignId('duplicate_of_id')->nullable()->after('is_existing')
                ->constrained('leads')->nullOnDelete();

            // The detection query: same customer, same campaign, recent first.
            $table->index(['customer_id', 'integration_id', 'created_at'], 'leads_repeat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_repeat_idx');
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropColumn('is_existing');
        });
    }
};
