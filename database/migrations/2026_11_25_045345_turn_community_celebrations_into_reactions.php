<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('community_post_celebrations', 'community_post_reactions');

        Schema::table('community_post_reactions', function (Blueprint $table) {
            $table->string('reaction', 20)->default('like')->after('user_id');
        });

        // Everything reacted to so far was a celebration.
        DB::table('community_post_reactions')->update(['reaction' => 'celebrate']);
    }

    public function down(): void
    {
        Schema::table('community_post_reactions', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });

        Schema::rename('community_post_reactions', 'community_post_celebrations');
    }
};
