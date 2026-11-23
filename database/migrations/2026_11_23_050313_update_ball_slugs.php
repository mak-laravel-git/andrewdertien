<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The starter balls were renamed, so their web addresses follow: /ponds/dream-ball, not /ponds/dream-pond.
     *
     * @var array<string, string>
     */
    private array $slugs = [
        'dream-pond' => 'dream-ball',
        'fun-pond' => 'fun-ball',
        'treats-pond' => 'treat-ball',
        'boost-pond' => 'boost-ball',
    ];

    public function up(): void
    {
        foreach ($this->slugs as $old => $new) {
            if (! DB::table('ponds')->where('slug', $new)->exists()) {
                DB::table('ponds')->where('slug', $old)->update(['slug' => $new]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->slugs as $old => $new) {
            DB::table('ponds')->where('slug', $new)->update(['slug' => $old]);
        }
    }
};
