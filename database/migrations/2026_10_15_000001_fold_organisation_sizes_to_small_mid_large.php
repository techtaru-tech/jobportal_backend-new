<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Company size is now Small / Mid / Large. Stored headcount bands are folded
 * onto the nearest of the three, so the enum cast can still read every row.
 */
return new class extends Migration
{
    private const MAP = [
        '1–10' => 'Small',
        '11–50' => 'Small',
        '51–200' => 'Mid',
        '201–500' => 'Mid',
        '500+' => 'Large',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('organisations')->where('size', $old)->update(['size' => $new]);
        }
        DB::table('organisations')
            ->whereNotNull('size')
            ->whereNotIn('size', ['Small', 'Mid', 'Large'])
            ->update(['size' => null]);
    }

    public function down(): void
    {
        DB::table('organisations')->where('size', 'Small')->update(['size' => '1–10']);
        DB::table('organisations')->where('size', 'Mid')->update(['size' => '51–200']);
        DB::table('organisations')->where('size', 'Large')->update(['size' => '500+']);
    }
};
