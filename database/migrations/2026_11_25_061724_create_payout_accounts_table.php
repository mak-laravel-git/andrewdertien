<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member's payout account at the payment provider, where the money from balls tossed
     * to them is sent. The provider holds the bank details and identity checks, never us.
     */
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('account_id')->unique();
            $table->string('country', 2);
            $table->boolean('details_submitted')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->json('requirements_due')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->text('stripe_connect_webhook_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn('stripe_connect_webhook_secret');
        });

        Schema::dropIfExists('payout_accounts');
    }
};
