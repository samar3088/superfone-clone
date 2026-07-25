<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings → Business Management → Tags
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('color', 20)->default('#0b5d51');
            $table->string('emoji', 8)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['is_hidden', 'id'], 'tags_visible_idx');
        });

        // Settings → CRM → Lead Stage
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('name', 80);
            $table->string('emoji', 8)->nullable();
            $table->enum('type', ['INITIAL', 'NONE', 'FINAL_POSITIVE', 'FINAL_NEGATIVE'])->default('NONE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sequence'], 'stages_active_seq_idx');
        });

        // Settings → CRM → Lead Group
        Schema::create('lead_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('type', 30)->default('DEFAULT');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Settings → CRM → Custom Fields
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80);
            $table->string('key', 80)->unique();
            $table->enum('field_type', ['text', 'number', 'date', 'dropdown', 'checkbox'])->default('text');
            $table->json('options')->nullable();      // choices for dropdown
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Settings → CRM → Field Priority Order
        Schema::create('field_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('section', 30);            // lead_tracking | additional
            $table->string('field_key', 80);
            $table->string('label', 80);
            $table->boolean('is_selected')->default(false);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['section', 'sequence'], 'field_priority_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_priorities');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('lead_groups');
        Schema::dropIfExists('lead_stages');
        Schema::dropIfExists('tags');
    }
};
