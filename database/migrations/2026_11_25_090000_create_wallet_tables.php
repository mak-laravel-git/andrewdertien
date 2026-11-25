<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members are paid through a wallet on the site instead of a payout account at the payment
     * provider: their share of each toss is added to their wallet, and an admin pays out what
     * they ask to withdraw. Every change to a wallet is a row in a ledger, so the balance is
     * always the sum of what happened and can be checked.
     */
    public function up(): void
    {
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            // Signed: money in is positive, money out is negative.
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            // Earnings can be held for a few days before they can be withdrawn.
            $table->timestamp('available_at');
            $table->foreignId('purchase_id')->nullable()->constrained('pond_purchases')->nullOnDelete();
            $table->unsignedBigInteger('withdrawal_request_id')->nullable()->index();
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A purchase is credited once and taken back once, however often it is processed.
            $table->unique(['purchase_id', 'type']);
            $table->index(['user_id', 'available_at']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('method', 40);
            // Bank or PayPal details: encrypted, and only an admin paying it out ever reads them.
            $table->text('details');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->unsignedInteger('minimum_withdrawal_cents')->default(1000);
            $table->unsignedSmallInteger('wallet_hold_days')->default(0);
        });

        $this->removeConnectPayouts();
    }

    /**
     * Stripe Connect payout accounts are gone, so the columns and table that served them go too.
     * A toss that was waiting to be sent is simply waiting to be credited to the wallet.
     */
    private function removeConnectPayouts(): void
    {
        Schema::dropIfExists('payout_accounts');

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['require_payouts', 'stripe_connect_webhook_secret']);
        });

        DB::table('pond_purchases')->where('payout_status', 'transferred')->update(['payout_status' => 'pending']);

        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropUnique(['transfer_id']);
        });

        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropColumn(['transfer_id', 'transfer_reversal_id']);
            $table->renameColumn('transferred_at', 'credited_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id', 'recipient_id', 'kind']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_entries');

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['minimum_withdrawal_cents', 'wallet_hold_days']);
        });
    }
};
