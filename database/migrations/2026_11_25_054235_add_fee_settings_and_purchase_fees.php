<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform fee and toss limits an admin sets in the dashboard, and the fee each
     * toss was charged. The fee is copied onto the purchase so changing the setting later
     * never rewrites history.
     */
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->decimal('platform_fee_percent', 5, 2)->default(10);
            $table->unsignedInteger('minimum_fee_cents')->default(50);
            $table->unsignedInteger('minimum_toss_cents')->default(100);
            $table->boolean('require_payouts')->default(true);
        });

        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->decimal('fee_percent', 5, 2)->nullable()->after('currency');
            $table->unsignedBigInteger('fee_cents')->default(0)->after('fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropColumn(['fee_percent', 'fee_cents']);
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_percent', 'minimum_fee_cents', 'minimum_toss_cents', 'require_payouts']);
        });
    }
};
