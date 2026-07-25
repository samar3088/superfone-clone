<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Work items raised against a lead — the "create a to-do" rules on each
 | integration finally have somewhere to put one.
 |
 | Kept separate from the lead rather than a column on it: a lead can carry a
 | first call and a follow-up at once, and each is completed by a different
 | person at a different time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // A task without its lead is meaningless, so it goes with it.
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // Not cascading: losing a member must not delete the work owed on
            // their leads — it needs reassigning, not erasing.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();

            // Which rule raised it, so a change to the rules can be traced.
            $table->string('trigger', 20)->default('manual');   // new_lead | existing_lead | manual

            $table->string('type', 40);
            $table->string('title', 150);
            $table->timestamp('due_at')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The list everyone actually looks at: my open work, soonest first.
            $table->index(['assigned_to', 'completed_at', 'due_at'], 'tasks_open_idx');
            $table->index(['lead_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
