<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks messages the site writes on a member's behalf, such as asking someone to set
     * up payouts, so the same request is not sent again and again.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('kind', 30)->nullable()->after('body');

            $table->index(['sender_id', 'recipient_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id', 'recipient_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
