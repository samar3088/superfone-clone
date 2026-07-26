<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Labels on a contact.
 |
 | Tags already existed but only ever attached to an integration, which stamps
 | them on arriving leads. This lets someone tag the person rather than the
 | campaign — "VIP", "Call back" — which is what the Contacts list shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            // The pair is the identity — a tag is on a contact or it is not.
            $table->primary(['customer_id', 'tag_id']);

            // Filtering asks "who has this tag?", so lead with the tag.
            $table->index(['tag_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag');
    }
};
