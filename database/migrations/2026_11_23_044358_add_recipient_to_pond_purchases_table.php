<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ball can be tossed to a member. Their name is snapshotted like the buyer's,
     * because financial records outlive the account.
     */
    public function up(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable()->after('buyer_email');
        });
    }

    public function down(): void
    {
        Schema::table('pond_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
            $table->dropColumn('recipient_name');
        });
    }
};
