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
        Schema::create('draft_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_round_id')->constrained('draft_rounds')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('pick_number');
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['draft_round_id', 'pick_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_skips');
    }
};
