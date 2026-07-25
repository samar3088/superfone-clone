<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            // Page preview shown on the integration's Configuration tab.
            $table->string('page_picture')->nullable()->after('page_name');
            $table->text('page_description')->nullable()->after('page_picture');

            // Source — "where are these leads coming from?"
            $table->string('source_type', 80)->nullable()->after('connected_account');
            $table->string('source', 120)->nullable()->after('source_type');

            // Assignment — how arriving leads are classified.
            $table->foreignId('lead_stage_id')->nullable()->after('source')
                ->constrained('lead_stages')->nullOnDelete();
            $table->foreignId('lead_group_id')->nullable()->after('lead_stage_id')
                ->constrained('lead_groups')->nullOnDelete();

            // "New lead" — what happens when one arrives.
            $table->boolean('new_lead_enabled')->default(false)->after('lead_group_id');
            $table->boolean('todo_enabled')->default(false)->after('new_lead_enabled');
            $table->string('todo_type', 40)->nullable()->after('todo_enabled');
            $table->string('todo_title', 150)->nullable()->after('todo_type');
            $table->unsignedInteger('todo_due_value')->nullable()->after('todo_title');
            $table->string('todo_due_unit', 10)->nullable()->after('todo_due_value');
            $table->boolean('notify_enabled')->default(false)->after('todo_due_unit');
        });

        // Labels applied to every lead from this campaign.
        Schema::create('integration_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['integration_id', 'tag_id']);
        });

        // Sync history for the integration's Logs tab.
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->enum('status', ['success', 'warning', 'error'])->default('success');
            $table->string('message', 255);
            $table->unsignedInteger('leads_fetched')->default(0);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['integration_id', 'created_at'], 'integration_logs_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_tag');

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_stage_id');
            $table->dropConstrainedForeignId('lead_group_id');
            $table->dropColumn([
                'page_picture', 'page_description', 'source_type', 'source',
                'new_lead_enabled', 'todo_enabled', 'todo_type', 'todo_title',
                'todo_due_value', 'todo_due_unit', 'notify_enabled',
            ]);
        });
    }
};
