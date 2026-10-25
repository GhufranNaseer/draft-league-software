<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->unsignedInteger('round_number')->nullable()->after('league_type');
        });

        $rounds = DB::table('draft_rounds')->orderBy('id')->get();
        $leagueCounts = [];
        foreach ($rounds as $round) {
            $league = $round->league_type ?? 'male';
            $leagueCounts[$league] = ($leagueCounts[$league] ?? 0) + 1;
            DB::table('draft_rounds')->where('id', $round->id)->update([
                'round_number' => $leagueCounts[$league],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->dropColumn('round_number');
        });
    }
};
