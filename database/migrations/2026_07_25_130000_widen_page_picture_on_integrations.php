<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facebook's CDN avatar URLs run past 530 characters, so varchar(255)
     * overflowed on the first real connect. Widen it so no provider's URL can
     * break the insert again.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->text('page_picture')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('page_picture')->nullable()->change();
        });
    }
};
