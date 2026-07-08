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
            $table->string('unit')->nullable();               // e.g. "/user /month"
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
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_purchases');
        Schema::dropIfExists('addons');
    }
};
