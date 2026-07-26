<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The rest of what the Create Contact form collects. All optional — a phone
 | number and a name are enough to start working someone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('website', 191)->nullable()->after('email');
            $table->string('business_name', 150)->nullable()->after('website');

            $table->string('house_no', 60)->nullable()->after('city');
            $table->string('address_1', 191)->nullable()->after('house_no');
            $table->string('address_2', 191)->nullable()->after('address_1');

            $table->text('additional_info')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'website', 'business_name', 'house_no',
                'address_1', 'address_2', 'additional_info',
            ]);
        });
    }
};
