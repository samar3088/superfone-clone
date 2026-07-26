<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Every way of reaching a customer — all their phone numbers and all their
 | email addresses, not just the first of each.
 |
 | This is what duplicate detection now searches. A person who enquired on one
 | number and later rings from another is one customer, and without this we
 | would only ever match the one we happened to store first.
 |
 | `customers.mobile` and `customers.email` stay as the primary of each, kept in
 | step with this table. They earn their place: lists, sorting and exports all
 | want one number per row without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('type', 10);              // phone | email
            $table->string('value', 150);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            /*
             | The real duplicate guard. Two customers cannot share a phone
             | number or an address, whichever slot it sits in — the database
             | refuses it rather than trusting every write path to check.
             */
            $table->unique(['type', 'value']);

            $table->index(['customer_id', 'type', 'is_primary'], 'channels_owner_idx');
        });

        /*
         | Backfill from what each customer already has, so detection covers
         | history from the first run rather than only new records.
         */
        DB::table('customers')->orderBy('id')->chunkById(500, function ($customers) {
            $rows = [];
            $now = now();

            foreach ($customers as $customer) {
                // Merge tombstones carry a suffixed mobile to dodge the old
                // unique index; they are not reachable and must not be indexed.
                if ($customer->merged_into_id !== null || $customer->deleted_at !== null) {
                    continue;
                }

                if (filled($customer->mobile)) {
                    $rows[] = [
                        'customer_id' => $customer->id,
                        'type' => 'phone',
                        'value' => $customer->mobile,
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (filled($customer->email)) {
                    $rows[] = [
                        'customer_id' => $customer->id,
                        'type' => 'email',
                        'value' => mb_strtolower($customer->email),
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('customer_channels')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_channels');
    }
};
