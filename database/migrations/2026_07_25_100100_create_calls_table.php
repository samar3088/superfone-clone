<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Call detail records. Populated by the VICI dial integration later; the
     * shape here is what the dashboard insights and exports read from.
     */
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // staff member
            $table->string('caller_number', 15);
            $table->enum('direction', ['incoming', 'outgoing']);
            // connected = talked; missed = never answered; received = inbound answered
            $table->enum('status', ['connected', 'missed', 'received', 'rejected'])->default('connected');
            $table->unsignedInteger('duration_sec')->default(0);
            $table->string('recording_url')->nullable();
            $table->string('external_id', 64)->nullable();   // VICI dial call id
            $table->timestamp('started_at');
            $table->timestamps();

            // Every insight query is "rows in a date range", often narrowed by
            // staff member or direction — these two cover all of them.
            $table->index(['started_at', 'direction', 'status'], 'calls_range_idx');
            $table->index(['user_id', 'started_at'], 'calls_staff_range_idx');
            $table->index(['customer_id', 'started_at']);
            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
