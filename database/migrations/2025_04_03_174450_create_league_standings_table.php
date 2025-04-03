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
        Schema::create('league_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained();
            $table->foreignId('team_id')->constrained();
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->decimal('win_percentage', 5, 3)->default(0);
            $table->unsignedSmallInteger('home_wins')->default(0);
            $table->unsignedSmallInteger('home_losses')->default(0);
            $table->unsignedSmallInteger('away_wins')->default(0);
            $table->unsignedSmallInteger('away_losses')->default(0);
            $table->unsignedInteger('points_scored')->default(0);
            $table->decimal('games_behind', 4, 1)->default(0);
            
            $table->timestamps();
            
            $table->unique(['season_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_standings');
    }
};
