<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->after('nationality');
            $table->index('league_type');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->after('email');
            $table->index('league_type');
        });

        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->after('id');
            $table->index(['league_type', 'status']);
        });

        Schema::table('league_round_configs', function (Blueprint $table) {
            $table->dropUnique('league_round_configs_round_number_unique');
            $table->enum('league_type', ['male', 'female'])->default('male')->after('id');
            $table->unique(['league_type', 'round_number'], 'league_round_configs_league_round_unique');
        });

        DB::table('participants')->whereNull('league_type')->update(['league_type' => 'male']);
        DB::table('teams')->whereNull('league_type')->update(['league_type' => 'male']);
        DB::table('draft_rounds')->whereNull('league_type')->update(['league_type' => 'male']);
        DB::table('league_round_configs')->whereNull('league_type')->update(['league_type' => 'male']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_round_configs', function (Blueprint $table) {
            $table->dropUnique('league_round_configs_league_round_unique');
            $table->dropColumn('league_type');
            $table->unique('round_number');
        });

        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->dropIndex('draft_rounds_league_type_status_index');
            $table->dropColumn('league_type');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('teams_league_type_index');
            $table->dropColumn('league_type');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex('participants_league_type_index');
            $table->dropColumn('league_type');
        });
    }
};
