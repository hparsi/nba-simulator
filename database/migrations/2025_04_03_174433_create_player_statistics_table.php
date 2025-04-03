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
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained();
            $table->unsignedSmallInteger('seconds_played')->default(0);
            $table->unsignedTinyInteger('points')->default(0);
            $table->unsignedTinyInteger('assists')->default(0);
            $table->unsignedTinyInteger('field_goals_made')->default(0);
            $table->unsignedTinyInteger('field_goals_attempted')->default(0);
            $table->unsignedTinyInteger('three_pointers_made')->default(0);
            $table->unsignedTinyInteger('three_pointers_attempted')->default(0);
            $table->unsignedTinyInteger('free_throws_made')->default(0);
            $table->unsignedTinyInteger('free_throws_attempted')->default(0);
            $table->decimal('field_goal_percentage', 5, 2)->nullable();
            $table->decimal('three_point_percentage', 5, 2)->nullable();
            $table->decimal('free_throw_percentage', 5, 2)->nullable();
            
            $table->timestamps();
            
            $table->unique(['game_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_statistics');
    }
};
