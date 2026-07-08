<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('virtual_number', 20);
            $table->string('caller', 20);
            $table->enum('direction', ['inbound', 'outbound', 'missed'])->default('inbound');
            $table->unsignedInteger('duration_sec')->default(0);
            $table->enum('status', ['completed', 'missed', 'rejected', 'voicemail'])->default('completed');
            $table->timestamp('called_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
