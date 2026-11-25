<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Balls are priced in US dollars, but a buyer abroad can pay in their own currency. The
     * purchase keeps what the buyer actually paid next to the dollar amount the platform receives.
     */
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->boolean('adaptive_pricing')->default(true);
        });

        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->string('presentment_currency', 3)->nullable()->after('currency');
            $table->unsignedBigInteger('presentment_amount_cents')->nullable()->after('presentment_currency');
        });
    }

    public function down(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropColumn(['presentment_currency', 'presentment_amount_cents']);
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn('adaptive_pricing');
        });
    }
};
