<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('mobile', 15);
            $table->string('email', 150)->nullable();
            $table->string('source', 40)->default('manual');   // manual | facebook | …
            $table->string('campaign', 150)->nullable();       // FB campaign / form name
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('viewed_at')->nullable();        // drives the unread bell count
            $table->softDeletes();
            $table->timestamps();

            // Unread-count lookups: "leads for me (or anyone) not yet viewed".
            $table->index(['viewed_at', 'assigned_to'], 'leads_unread_idx');
            $table->index(['source', 'created_at']);
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
