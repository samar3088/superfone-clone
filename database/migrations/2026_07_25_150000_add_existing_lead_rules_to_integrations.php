<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The New Lead rule set, mirrored for leads we have seen before.
 |
 | A repeat enquiry usually deserves different handling from a first one — a
 | follow-up call rather than a first call — so the two carry their own to-do
 | and their own notification delay rather than sharing one set of fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            // How long after arrival the new-lead notification fires.
            $table->unsignedSmallInteger('notify_value')->nullable()->after('notify_enabled');
            $table->string('notify_unit', 10)->nullable()->after('notify_value');

            $table->boolean('existing_lead_enabled')->default(false)->after('notify_unit');
            $table->boolean('existing_todo_enabled')->default(false)->after('existing_lead_enabled');
            $table->string('existing_todo_type', 40)->nullable()->after('existing_todo_enabled');
            $table->string('existing_todo_title', 150)->nullable()->after('existing_todo_type');
            $table->unsignedSmallInteger('existing_todo_due_value')->nullable()->after('existing_todo_title');
            $table->string('existing_todo_due_unit', 10)->nullable()->after('existing_todo_due_value');
            $table->boolean('existing_notify_enabled')->default(false)->after('existing_todo_due_unit');
            $table->unsignedSmallInteger('existing_notify_value')->nullable()->after('existing_notify_enabled');
            $table->string('existing_notify_unit', 10)->nullable()->after('existing_notify_value');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'notify_value', 'notify_unit',
                'existing_lead_enabled', 'existing_todo_enabled', 'existing_todo_type',
                'existing_todo_title', 'existing_todo_due_value', 'existing_todo_due_unit',
                'existing_notify_enabled', 'existing_notify_value', 'existing_notify_unit',
            ]);
        });
    }
};
