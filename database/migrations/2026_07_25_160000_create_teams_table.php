<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The business itself: its legal name, the virtual number customers dial, and
 | whatever plan limits apply.
 |
 | One row today. It is a table rather than config because the number arrives
 | later from a telephony provider, and plan limits are the sort of thing an
 | owner changes without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('status', 20)->default('active');

            // Populated when a virtual number is provisioned; until then a
            // customer has no single number to dial.
            $table->string('virtual_number', 20)->nullable()->unique();
            $table->string('number_provider', 40)->nullable();
            $table->timestamp('number_assigned_at')->nullable();

            // Null means no ceiling — better than inventing a plan we have not sold.
            $table->unsignedInteger('staff_limit')->nullable();
            $table->unsignedInteger('lead_limit')->nullable();
            $table->date('plan_renews_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
