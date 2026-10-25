<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_round_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('round_number')->unique();
            $table->json('team_pick_order');   // ordered array of team IDs for first pick rotation
            $table->boolean('is_manually_set')->default(false); // true if admin overrode auto-snake order
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_round_configs');
    }
};
