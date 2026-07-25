<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A small key/value store for things an owner can change at runtime and that
 | do not deserve a table of their own — feature toggles, and credentials that
 | should not sit in .env where a stack trace or a stray `config:cache` dump can
 | expose them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            // Encrypted values are ciphertext in `value`; readers must decrypt.
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
