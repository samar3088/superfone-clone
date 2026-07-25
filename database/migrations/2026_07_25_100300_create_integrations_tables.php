<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A connected lead source — for now, a Facebook page + lead form.
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('facebook');
            $table->string('name', 120);
            $table->string('external_page_id', 64)->nullable();
            $table->string('page_name', 150)->nullable();
            $table->string('external_form_id', 64)->nullable();
            $table->string('form_name', 150)->nullable();
            $table->string('connected_account', 150)->nullable();
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider', 'status'], 'integrations_provider_status_idx');
        });

        /*
         | Which team members receive leads from a campaign. Mapping happens
         | when the campaign is connected; leads are then distributed
         | round-robin across the mapped members.
         */
        Schema::create('integration_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['integration_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_user');
        Schema::dropIfExists('integrations');
    }
};
