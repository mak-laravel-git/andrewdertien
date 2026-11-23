<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The starter "ponds" are now balls: there is only one Pond, and people toss balls into it.
     *
     * @var array<string, string>
     */
    private array $names = [
        'Dream Pond' => 'Dream Ball',
        'Fun Pond' => 'Fun Ball',
        'Treats Pond' => 'Treat Ball',
        'Boost Pond' => 'Boost Ball',
    ];

    public function up(): void
    {
        foreach ($this->names as $old => $new) {
            DB::table('ponds')->where('name', $old)->update(['name' => $new]);
            DB::table('pond_purchases')->where('pond_name', $old)->update(['pond_name' => $new]);
        }

        DB::table('ponds')->where('name', 'Dream Ball')->update(['description' => 'A ball for dreams, goals and aspirations, whether big or small.']);
        DB::table('ponds')->where('name', 'Fun Ball')->update(['description' => 'A ball for hobbies, interests and experiences you would love to have.']);
        DB::table('ponds')->where('name', 'Treat Ball')->update(['description' => 'A ball for everyday little pleasures, such as coffee, a meal or a gallon of gas.']);
        DB::table('ponds')->where('name', 'Boost Ball')->update(['description' => 'A ball for when a little help would make a difference: groceries, transportation, bills.']);
    }

    public function down(): void
    {
        foreach ($this->names as $old => $new) {
            DB::table('ponds')->where('name', $new)->update(['name' => $old]);
            DB::table('pond_purchases')->where('pond_name', $new)->update(['pond_name' => $old]);
        }
    }
};
