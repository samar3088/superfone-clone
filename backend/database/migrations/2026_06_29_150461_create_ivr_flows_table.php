<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->text('greeting')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->timestamps();
        });

        Schema::create('ivr_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ivr_flow_id')->constrained('ivr_flows')->cascadeOnDelete();
            $table->string('key_press', 2);
            $table->string('label');
            $table->enum('action', ['forward', 'voicemail', 'submenu', 'hangup', 'repeat'])->default('forward');
            $table->string('destination')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_options');
        Schema::dropIfExists('ivr_flows');
    }
};
