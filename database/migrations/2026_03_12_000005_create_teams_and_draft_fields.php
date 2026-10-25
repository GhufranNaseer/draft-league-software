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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_code', 10)->nullable()->unique();
            $table->string('captain_name')->nullable();
            $table->string('email')->unique();
            $table->unsignedTinyInteger('max_players')->default(11);
            $table->string('logo')->nullable();
            $table->string('color', 7)->default('#6c757d');
            $table->timestamps();
        });

        Schema::table('participants', function (Blueprint $table) {
            if (!Schema::hasColumn('participants', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('category_id')->constrained('teams')->nullOnDelete();
            }

            if (!Schema::hasColumn('participants', 'drafted_at')) {
                $table->timestamp('drafted_at')->nullable()->after('team_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (Schema::hasColumn('participants', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }

            if (Schema::hasColumn('participants', 'drafted_at')) {
                $table->dropColumn('drafted_at');
            }
        });

        Schema::dropIfExists('teams');
    }
};
