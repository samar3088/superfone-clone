<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 15);
            $table->string('code_hash');                  // never store the OTP in plain text
            $table->string('purpose', 20)->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            // Verification looks up the newest live code for a mobile+purpose.
            $table->index(['mobile', 'purpose', 'consumed_at', 'expires_at'], 'otp_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
