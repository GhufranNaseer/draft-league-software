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
            $table->boolean('is_waiting_for_admin')->default(false)->after('is_paused');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_rounds', function (Blueprint $table) {
            $table->dropColumn('is_waiting_for_admin');
        });
    }
};
