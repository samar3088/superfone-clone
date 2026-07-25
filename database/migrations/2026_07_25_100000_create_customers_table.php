<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer is the real person. The same person enquiring through two
     * campaigns produces two leads but only ever one customer row, matched on
     * mobile or email — both of which are unique.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('mobile', 15)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();

            // Set when this record is folded into another during a merge, so
            // history is preserved rather than destroyed.
            $table->foreignId('merged_into_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();

            $table->timestamp('last_activity_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['merged_into_id', 'last_activity_at'], 'customers_active_activity_idx');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
