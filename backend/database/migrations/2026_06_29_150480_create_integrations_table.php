<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lead-source connections (Facebook Lead Ads, IndiaMART, webhooks, …).
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('provider', 40);            // facebook, indiamart, webhook, …
            $table->string('name');                    // user-given label
            $table->string('page_name')->nullable();   // e.g. FB page
            $table->string('form_name')->nullable();   // e.g. FB lead form
            $table->string('connected_account')->nullable(); // e.g. "Shank K Vasudev"
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
