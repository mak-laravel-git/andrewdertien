<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a refund gave back to the buyer, how the member's payout was taken back, and any
     * dispute (chargeback) the buyer's bank opened.
     */
    public function up(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded_cents')->nullable()->after('refunded_at');
            $table->string('transfer_reversal_id')->nullable()->after('transfer_id');
            $table->string('dispute_id')->nullable()->after('transferred_at')->index();
            $table->string('dispute_status')->nullable()->after('dispute_id');
            $table->timestamp('disputed_at')->nullable()->after('dispute_status');
        });
    }

    public function down(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropIndex(['dispute_id']);
            $table->dropColumn(['refunded_cents', 'transfer_reversal_id', 'dispute_id', 'dispute_status', 'disputed_at']);
        });
    }
};
