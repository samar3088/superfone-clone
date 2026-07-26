<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Dated notes against a contact.
 |
 | The customer is always required and the lead never is. That is the whole
 | design: someone rings in before any enquiry exists, and the note still has
 | to land somewhere. When they are enquiring about something specific, the
 | lead is recorded as well — but the note belongs to the person either way,
 | so nothing is lost when a lead is later merged or removed.
 |
 | Separate from customers.notes, which is a single free-text field on the
 | contact record, filled once at creation and edited in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // A note without its contact is meaningless, so it goes with it.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            /*
             | Nulled rather than cascaded, so losing the lead demotes the note
             | to a contact-level one instead of erasing it.
             |
             | In practice leads are archived rather than erased, and the note
             | simply stops finding a lead to name — it reads as a note about
             | the contact either way. This is the backstop for a hard delete.
             */
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            // Kept even if the author leaves — "written by someone who has left"
            // is more useful than a note that quietly loses its history.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            $table->timestamps();

            // The timeline everyone reads: this contact's notes, newest first.
            $table->index(['customer_id', 'created_at']);
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
