<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchasable add-ons catalog (Extra team member, Automation pack, …).
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon', 8)->default('🧩');
            $table->decimal('price', 10, 2);
            $table->string('unit')->nullable();          // e.g. "/user /month", "/month"
            $table->boolean('quantity_based')->default(true); // stepper vs single "Add"
            $table->timestamps();
        });

        Schema::create('addon_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        // Live/in-flight calls, provider-agnostic. A telephony driver (simulated
        // now, Exotel/Twilio later) creates and updates these via the webhook.
        Schema::create('live_calls', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->default('simulated');
            $table->string('provider_call_id')->nullable();   // real provider's call SID
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('virtual_number', 20);
            $table->string('caller', 20);
            // Sticky routing: agent who last spoke with this caller rings first.
            $table->foreignId('sticky_agent_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->boolean('ring_all')->default(false);      // true once escalated to everyone
            $table->foreignId('answered_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->enum('status', ['ringing', 'in_progress', 'completed', 'missed'])->default('ringing');
            $table->timestamp('started_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_calls');
        Schema::dropIfExists('addon_purchases');
        Schema::dropIfExists('addons');
    }
};
