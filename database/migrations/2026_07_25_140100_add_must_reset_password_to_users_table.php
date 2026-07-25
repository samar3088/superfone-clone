<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when we email someone a temporary password, cleared once they
            // choose their own. Keeps the emailed password effectively
            // single-use rather than a permanent credential sitting in an inbox.
            $table->boolean('must_reset_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_reset_password');
        });
    }
};
