<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the member's share of a toss came to, and whether it has been sent to them. The
     * processing fee and the share are only known once the payment provider has settled the
     * payment, so they stay empty until then.
     */
    public function up(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('processing_fee_cents')->nullable()->after('fee_cents');
            $table->unsignedBigInteger('net_cents')->nullable()->after('processing_fee_cents');
            $table->string('payout_status')->default('none')->after('net_cents')->index();
            $table->string('transfer_id')->nullable()->unique()->after('payout_status');
            $table->string('payout_failure_message')->nullable()->after('transfer_id');
            $table->unsignedSmallInteger('payout_attempts')->default(0)->after('payout_failure_message');
            $table->timestamp('transferred_at')->nullable()->after('payout_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'processing_fee_cents', 'net_cents', 'payout_status', 'transfer_id',
                'payout_failure_message', 'payout_attempts', 'transferred_at',
            ]);
        });
    }
};
