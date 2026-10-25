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
        Schema::create('draft_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('start_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('current_team_id')->constrained('teams')->cascadeOnDelete();
            $table->json('pick_order');
            $table->json('higher_category_ids')->nullable();
            $table->unsignedTinyInteger('picks_per_team')->default(1);
            $table->unsignedSmallInteger('turn_time_seconds')->default(180);
            $table->unsignedInteger('current_pick_number')->default(1);
            $table->unsignedInteger('total_picks_planned');
            $table->timestamp('current_turn_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('draft_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_round_id')->constrained('draft_rounds')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->unsignedInteger('pick_number');
            $table->timestamp('picked_at');
            $table->timestamps();

            $table->unique(['draft_round_id', 'participant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_picks');
        Schema::dropIfExists('draft_rounds');
    }
};
