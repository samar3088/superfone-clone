<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('integration_id')->nullable()->after('campaign')
                ->constrained('integrations')->nullOnDelete();
            $table->foreignId('lead_stage_id')->nullable()->after('integration_id')
                ->constrained('lead_stages')->nullOnDelete();
            $table->foreignId('lead_group_id')->nullable()->after('lead_stage_id')
                ->constrained('lead_groups')->nullOnDelete();

            $table->json('custom_data')->nullable()->after('lead_group_id');

            /*
             | Optimistic lock. Every status change submits the version it was
             | read at; if another member changed the lead meanwhile the update
             | is rejected instead of silently overwriting their work.
             */
            $table->unsignedInteger('version')->default(1)->after('custom_data');
            $table->foreignId('last_updated_by')->nullable()->after('version')
                ->constrained('users')->nullOnDelete();

            $table->index(['customer_id', 'created_at'], 'leads_customer_idx');
            $table->index(['lead_stage_id', 'created_at'], 'leads_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('integration_id');
            $table->dropConstrainedForeignId('lead_stage_id');
            $table->dropConstrainedForeignId('lead_group_id');
            $table->dropConstrainedForeignId('last_updated_by');
            $table->dropColumn(['custom_data', 'version']);
        });
    }
};
