<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['sales', 'receptionist', 'whatsapp', 'payment', 'manager'])->default('receptionist');
            $table->enum('channel', ['call', 'whatsapp', 'both'])->default('call');
            $table->enum('status', ['active', 'paused', 'draft'])->default('draft');
            $table->text('persona')->nullable();
            $table->unsignedInteger('handled')->default(0);
            $table->decimal('resolution_rate', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
