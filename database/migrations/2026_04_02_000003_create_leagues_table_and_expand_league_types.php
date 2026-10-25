<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('leagues')->upsert([
            [
                'name' => 'KTPL (Mens League)',
                'slug' => 'male',
                'description' => 'Default men\'s league migrated from the original setup.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'WTPL (Women League)',
                'slug' => 'female',
                'description' => 'Default women\'s league migrated from the original setup.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'description', 'is_active', 'sort_order', 'updated_at']);

        Schema::table('participants', function (Blueprint $table) {
            $table->string('league_type', 100)->default('male')->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('league_type', 100)->default('male')->change();
        });

        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->string('league_type', 100)->default('male')->change();
        });

        Schema::table('league_round_configs', function (Blueprint $table) {
            $table->string('league_type', 100)->default('male')->change();
        });
    }

    public function down(): void
    {
        foreach (['participants', 'teams', 'draft_rounds', 'league_round_configs'] as $tableName) {
            DB::table($tableName)
                ->whereNotIn('league_type', ['male', 'female'])
                ->update(['league_type' => 'male']);
        }

        Schema::table('participants', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->change();
        });

        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->change();
        });

        Schema::table('league_round_configs', function (Blueprint $table) {
            $table->enum('league_type', ['male', 'female'])->default('male')->change();
        });

        Schema::dropIfExists('leagues');
    }
};
