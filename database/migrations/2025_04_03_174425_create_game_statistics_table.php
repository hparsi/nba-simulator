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
        Schema::create('game_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained();
            $table->boolean('is_home_team');
            $table->integer('q1_score')->default(0);
            $table->integer('q2_score')->default(0);
            $table->integer('q3_score')->default(0);
            $table->integer('q4_score')->default(0);
            $table->integer('ot_score')->default(0);
            $table->integer('field_goals_made')->default(0);
            $table->integer('field_goals_attempted')->default(0);
            $table->integer('three_pointers_made')->default(0);
            $table->integer('three_pointers_attempted')->default(0);
            $table->integer('free_throws_made')->default(0);
            $table->integer('free_throws_attempted')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('attack_count')->default(0);
            $table->decimal('field_goal_percentage', 5, 2)->nullable();
            $table->decimal('three_point_percentage', 5, 2)->nullable();
            $table->decimal('free_throw_percentage', 5, 2)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_statistics');
    }
};
