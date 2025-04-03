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
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained();
            $table->foreignId('player_id')->nullable()->constrained();
            $table->foreignId('secondary_player_id')->nullable()->constrained('players');
            
            $table->enum('event_type', [
                'game_start', 'game_end', 'quarter_start', 'quarter_end',
                'field_goal', 'three_pointer', 'free_throw',
                'rebound', 'assist', 'steal', 'block', 'turnover',
                'foul', 'substitution', 'timeout'
            ]);
            
            $table->smallInteger('score_value')->default(0);
            $table->unsignedTinyInteger('quarter');
            $table->unsignedSmallInteger('quarter_time');
            $table->text('description')->nullable();
            
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
