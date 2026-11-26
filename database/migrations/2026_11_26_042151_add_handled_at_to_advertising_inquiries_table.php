<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_inquiries', function (Blueprint $table) {
            $table->timestamp('handled_at')->nullable()->after('message');
            $table->index('handled_at');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_inquiries', function (Blueprint $table) {
            $table->dropIndex(['handled_at']);
            $table->dropColumn('handled_at');
        });
    }
};
