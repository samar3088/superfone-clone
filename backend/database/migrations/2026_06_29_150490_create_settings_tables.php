<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contact tags (Settings → Business Management → Tags).
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');           // may include a leading emoji
            $table->string('color', 20)->default('#6366f1');
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });

        // Custom lead stages (Settings → CRM Settings → Lead Stage).
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('name');
            $table->enum('type', ['INITIAL', 'NONE', 'FINAL_POSITIVE', 'FINAL_NEGATIVE'])->default('NONE');
            $table->unsignedInteger('contacts_attached')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('DEFAULT');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label');
            $table->string('field_type', 30)->default('text');
            $table->timestamps();
        });

        // Per-org call/CRM settings. sticky_agent drives the CallRouter.
        Schema::create('org_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->boolean('sticky_agent')->default(true);
            $table->string('sticky_fallback', 30)->default('ringing_order'); // or 'end_call'
            $table->boolean('ivr_enabled')->default(false);
            $table->boolean('recording_enabled')->default(true);
            $table->json('priority_fields')->nullable();
            $table->timestamps();
        });

        // Ringing order groups per org, for open vs closed hours.
        Schema::create('ring_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('hours', ['open', 'closed'])->default('open');
            $table->unsignedInteger('group_no')->default(1);
            $table->foreignId('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ring_orders');
        Schema::dropIfExists('org_settings');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('lead_groups');
        Schema::dropIfExists('lead_stages');
        Schema::dropIfExists('tags');
    }
};
