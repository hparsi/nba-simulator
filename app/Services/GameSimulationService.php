<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameStatistic;
use App\Models\Player;
use App\Models\PlayerStatistic;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameSimulationService
{
    // Basketball constants
    private const QUARTER_LENGTH_SECONDS = 720; // 12 minutes
    private const QUARTER_COUNT = 4;
    private const OVERTIME_LENGTH_SECONDS = 300; // 5 minutes
    private const MINUTE_SECONDS = 60; // Seconds in a game minute
    private const SHOT_CLOCK_SECONDS = 24; // NBA shot clock is 24 seconds

    // Probability constants
    private const PROBABILITY_HOME_ADVANTAGE = 0.55; // Home team wins 55% of possessions
    private const PROBABILITY_THREE_POINTER = 0.35; // 35% of shots are 3-pointers
    private const PROBABILITY_FIELD_GOAL_MADE = 0.45; // 45% field goal percentage
    private const PROBABILITY_THREE_POINTER_MADE = 0.35; // 35% three point percentage
    private const PROBABILITY_FREE_THROW_MADE = 0.75; // 75% free throw percentage
    private const PROBABILITY_FOUL = 0.15; // 15% chance of foul on a possession
    private const PROBABILITY_SHOOTING_FOUL = 0.6; // 60% of fouls are shooting fouls
    private const PROBABILITY_ASSIST = 0.6; // 60% of made shots have assists
    private const PROBABILITY_TURNOVER = 0.12; // 12% chance of turnover per possession

    // For tracking active players in the game
    private array $homeTeamActivePlayers = [];
    private array $awayTeamActivePlayers = [];
    
    // Stats tracking
    private array $playerStats = [];
    private array $homeTeamStats = [];
    private array $awayTeamStats = [];
    
    /**
     * Initialize a new game (for real-time simulation)
     */
    public function initializeGame(Game $game): void
    {
        try {
            DB::beginTransaction();
            
            $game->status = 'in_progress';
            $game->started_at = now();
            $game->current_quarter = 1;
            $game->quarter_time_seconds = self::QUARTER_LENGTH_SECONDS;
            $game->home_team_score = 0;
            $game->away_team_score = 0;
            $game->save();
            
            $this->initializeTeamStats($game);
            
            $this->initializePlayerStats($game);
            
            $this->createGameEvent($game, null, null, 'game_start', 0, 1, self::QUARTER_LENGTH_SECONDS, 
                'Game started between ' . $game->homeTeam->name . ' and ' . $game->awayTeam->name, 0, 0);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error initializing game: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * End a game (for real-time simulation)
     */
    public function endGame(Game $game): void
    {
        try {
            DB::beginTransaction();
            
            $game->status = 'completed';
            $game->ended_at = now();
            $game->save();
            
            $winningTeam = $game->home_team_score > $game->away_team_score 
                ? $game->homeTeam 
                : $game->awayTeam;
                
            $description = 'Game ended. ' . $winningTeam->name . ' wins ' . 
                $game->home_team_score . '-' . $game->away_team_score;
                
            $this->createGameEvent($game, null, null, 'game_end', 0, $game->current_quarter, 0, 
                $description, $game->home_team_score, $game->away_team_score);
            
            $this->updatePlayerStats();
            
            $this->finalizeTeamStats($game);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error ending game: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Simulate one minute of game time (for real-time simulation)
     * 
     * @param Game $game The game to simulate
     * @return array Updates that occurred during this minute
     */
    public function simulateMinute(Game $game): array
    {
        $updates = [
            'events' => [],
            'initialScore' => [
                'home' => $game->home_team_score,
                'away' => $game->away_team_score
            ],
            'finalScore' => null
        ];
        
        try {
            $totalMinutesPlayed = $this->calculateTotalMinutesPlayed($game);
            $currentQuarter = $this->minuteToQuarter($totalMinutesPlayed);
            $quarterTimeRemaining = $this->calculateQuarterTimeRemaining($totalMinutesPlayed);
            
            if ($game->current_quarter != $currentQuarter) {
                $previousQuarter = $game->current_quarter;
                $game->current_quarter = $currentQuarter;
                $game->quarter_time_seconds = $quarterTimeRemaining;
                $game->save();
                
                $this->createQuarterEndEvent($game, $previousQuarter);
                
                $event = $this->createQuarterStartEvent($game, $currentQuarter);
                $updates['events'][] = $this->formatEventForUpdate($event);
            } else {
                $game->quarter_time_seconds = $quarterTimeRemaining;
                $game->save();
            }
            
            $possessionsThisMinute = rand(3, 5);
            
            for ($i = 0; $i < $possessionsThisMinute; $i++) {
                $timeWithinMinute = self::MINUTE_SECONDS / $possessionsThisMinute * $i;
                $secondsRemaining = $quarterTimeRemaining - $timeWithinMinute;
                
                if ($secondsRemaining < 0) {
                    $secondsRemaining = 0;
                }

                $events = $this->simulatePossessionWithEvents($game, $currentQuarter, $secondsRemaining);
                
                foreach ($events as $event) {
                    $updates['events'][] = $this->formatEventForUpdate($event);
                }
            }
            
            $updates['finalScore'] = [
                'home' => $game->home_team_score,
                'away' => $game->away_team_score
            ];
            
            return $updates;
        } catch (\Exception $e) {
            Log::error("Error simulating minute: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate total minutes played in the game
     */
    private function calculateTotalMinutesPlayed(Game $game): int
    {
        $totalGameMinutes = (int) (($game->current_quarter - 1) * 12);
        
        $quarterSecondsPlayed = self::QUARTER_LENGTH_SECONDS - $game->quarter_time_seconds;
        $quarterMinutesPlayed = (int) ceil($quarterSecondsPlayed / 60);
        
        return $totalGameMinutes + $quarterMinutesPlayed;
    }
    
    /**
     * Convert a minute number (0-47) to a quarter (1-4)
     */
    private function minuteToQuarter(int $minute): int
    {
        return (int) floor($minute / 12) + 1;
    }
    
    /**
     * Calculate time remaining in the current quarter based on total minutes played
     */
    private function calculateQuarterTimeRemaining(int $totalMinutesPlayed): int
    {
        $quarterMinute = $totalMinutesPlayed % 12;
        return (12 - $quarterMinute - 1) * 60; // Convert to seconds remaining in quarter
    }
    
    /**
     * Create a quarter start event
     */
    private function createQuarterStartEvent(Game $game, int $quarter): GameEvent
    {
        $quarterText = $quarter <= 4 ? "Quarter $quarter" : "Overtime " . ($quarter - 4);
        
        return $this->createGameEvent(
            $game, 
            null, 
            null, 
            'quarter_start', 
            0, 
            $quarter, 
            self::QUARTER_LENGTH_SECONDS, 
            "$quarterText started", 
            $game->home_team_score, 
            $game->away_team_score
        );
    }
    
    /**
     * Create a quarter end event
     */
    private function createQuarterEndEvent(Game $game, int $quarter): GameEvent
    {
        $quarterText = $quarter <= 4 ? "Quarter $quarter" : "Overtime " . ($quarter - 4);
        
        return $this->createGameEvent(
            $game, 
            null, 
            null, 
            'quarter_end', 
            0, 
            $quarter, 
            0, 
            "$quarterText ended", 
            $game->home_team_score, 
            $game->away_team_score
        );
    }
    
    /**
     * Simulate a possession and return the events that occurred
     */
    private function simulatePossessionWithEvents(Game $game, int $quarter, int $timeRemaining): array
    {
        $events = [];
        
        $isHomePossession = (mt_rand(1, 100) / 100) <= self::PROBABILITY_HOME_ADVANTAGE;
        
        $offensiveTeam = $isHomePossession ? $game->homeTeam : $game->awayTeam;
        $defensiveTeam = $isHomePossession ? $game->awayTeam : $game->homeTeam;
        
        $activePlayers = $isHomePossession ? $this->homeTeamActivePlayers : $this->awayTeamActivePlayers;
        
        if (empty($activePlayers)) {
            Log::warning("No active players found for {$offensiveTeam->name} (Team ID: {$offensiveTeam->id}) in game {$game->id}. Re-initializing player stats.");
            $this->initializePlayerStats($game);
            
            $activePlayers = $isHomePossession ? $this->homeTeamActivePlayers : $this->awayTeamActivePlayers;
            
            if (empty($activePlayers)) {
                Log::error("Failed to initialize active players for {$offensiveTeam->name} in game {$game->id}. Team has " . 
                           $offensiveTeam->players()->count() . " players in database.");
                return $events;
            }
        }
        
        $playerIndex = array_rand($activePlayers);
        $playerId = $activePlayers[$playerIndex];
        $offensivePlayer = Player::find($playerId);
        
        if (!$offensivePlayer) {
            Log::error("Could not find player with ID {$playerId} for team {$offensiveTeam->name} in game {$game->id}");
            return $events;
        }
        
        if ((mt_rand(1, 100) / 100) <= self::PROBABILITY_TURNOVER) {
            $event = $this->simulateTurnoverWithEvent($game, $offensiveTeam, $offensivePlayer, $quarter, $timeRemaining);
            $events[] = $event;
            return $events;
        }
        
        if ((mt_rand(1, 100) / 100) <= self::PROBABILITY_FOUL) {
            $defensiveActivePlayers = $isHomePossession ? $this->awayTeamActivePlayers : $this->homeTeamActivePlayers;
                        
            if (empty($defensiveActivePlayers)) {
                Log::warning("No active defensive players found for {$defensiveTeam->name} (Team ID: {$defensiveTeam->id}) in game {$game->id}.");
                return $events;
            }
            
            $defPlayerIndex = array_rand($defensiveActivePlayers);
            $defPlayerId = $defensiveActivePlayers[$defPlayerIndex];
            $defensivePlayer = Player::find($defPlayerId);
            
            if (!$defensivePlayer) {
                Log::error("Could not find defensive player with ID {$defPlayerId} for team {$defensiveTeam->name} in game {$game->id}");
                return $events;
            }
            
            if ((mt_rand(1, 100) / 100) <= self::PROBABILITY_SHOOTING_FOUL) {
                $foulEvents = $this->simulateShootingFoulWithEvents($game, $offensiveTeam, $offensivePlayer, $defensivePlayer, $quarter, $timeRemaining);
                $events = array_merge($events, $foulEvents);
            } else {
                $event = $this->simulateNonShootingFoulWithEvent($game, $offensiveTeam, $offensivePlayer, $defensivePlayer, $quarter, $timeRemaining);
                $events[] = $event;
            }
            return $events;
        }
        
        $shotEvents = $this->simulateShotWithEvents($game, $offensiveTeam, $offensivePlayer, $quarter, $timeRemaining);
        $events = array_merge($events, $shotEvents);
        
        return $events;
    }
    
    /**
     * Simulate a turnover and return the event
     */
    private function simulateTurnoverWithEvent(Game $game, Team $team, Player $player, int $quarter, int $timeRemaining): GameEvent
    {
        $description = $player->first_name . ' ' . $player->last_name . ' committed a turnover';
        
        $event = $this->createGameEvent($game, $team, $player, 'turnover', 0, $quarter, $timeRemaining, 
            $description, $game->home_team_score, $game->away_team_score);
        
        $teamStats = $team->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        
        if (!isset($teamStats['turnovers'])) {
            $teamStats['turnovers'] = 0;
        }
        
        $teamStats['turnovers']++;
        
        if ($team->id === $game->home_team_id) {
            $this->homeTeamStats = $teamStats;
        } else {
            $this->awayTeamStats = $teamStats;
        }
        
        $this->updateTeamStats($game, $team->id === $game->home_team_id);
        
        return $event;
    }
    
    /**
     * Simulate a shooting foul and return the events
     */
    private function simulateShootingFoulWithEvents(Game $game, Team $team, Player $player, Player $defensivePlayer, int $quarter, int $timeRemaining): array
    {
        $events = [];
        
        $description = $defensivePlayer->first_name . ' ' . $defensivePlayer->last_name . ' committed a shooting foul on ' .
            $player->first_name . ' ' . $player->last_name;
            
        $defensiveTeam = $defensivePlayer->team;
        
        $event = $this->createGameEvent($game, $defensiveTeam, $defensivePlayer, 'foul', 0, $quarter, $timeRemaining, 
            $description, $game->home_team_score, $game->away_team_score, $player);
            
        $events[] = $event;
        
        $freeThrowCount = (mt_rand(1, 100) / 100) <= self::PROBABILITY_THREE_POINTER ? 3 : 2;
        
        $madeCount = 0;
        for ($i = 0; $i < $freeThrowCount; $i++) {
            $isMade = (mt_rand(1, 100) / 100) <= self::PROBABILITY_FREE_THROW_MADE;
            
            if ($isMade) {
                $madeCount++;
                
                if ($team->id === $game->home_team_id) {
                    $game->home_team_score++;
                } else {
                    $game->away_team_score++;
                }
                
                $description = $player->first_name . ' ' . $player->last_name . ' made free throw ' . ($i + 1) . ' of ' . $freeThrowCount;
                $ftEvent = $this->createGameEvent($game, $team, $player, 'free_throw', 1, $quarter, $timeRemaining, 
                    $description, $game->home_team_score, $game->away_team_score);
                $events[] = $ftEvent;
                    
                $key = $this->getPlayerStatsKey($player->id);
                $this->playerStats[$key]['points']++;
                $this->playerStats[$key]['free_throws_made']++;
                $this->playerStats[$key]['free_throws_attempted']++;
            } else {
                $description = $player->first_name . ' ' . $player->last_name . ' missed free throw ' . ($i + 1) . ' of ' . $freeThrowCount;
                $ftEvent = $this->createGameEvent($game, $team, $player, 'free_throw', 0, $quarter, $timeRemaining, 
                    $description, $game->home_team_score, $game->away_team_score);
                $events[] = $ftEvent;
                    
                $key = $this->getPlayerStatsKey($player->id);
                $this->playerStats[$key]['free_throws_attempted']++;
            }
            
            $game->save();
        }
        
        $teamStats = $team->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        
        if (!isset($teamStats['free_throws_attempted'])) {
            $teamStats['free_throws_attempted'] = 0;
        }
        if (!isset($teamStats['free_throws_made'])) {
            $teamStats['free_throws_made'] = 0;
        }
        
        $teamStats['free_throws_attempted'] += $freeThrowCount;
        $teamStats['free_throws_made'] += $madeCount;
        
        if ($team->id === $game->home_team_id) {
            $this->homeTeamStats = $teamStats;
        } else {
            $this->awayTeamStats = $teamStats;
        }
        
        $this->updateTeamStats($game, $team->id === $game->home_team_id);
        
        $defTeamStats = $defensiveTeam->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        if (!isset($defTeamStats['fouls'])) {
            $defTeamStats['fouls'] = 0;
        }
        $defTeamStats['fouls']++;
        
        if ($defensiveTeam->id === $game->home_team_id) {
            $this->homeTeamStats = $defTeamStats;
        } else {
            $this->awayTeamStats = $defTeamStats;
        }
        
        return $events;
    }
    
    /**
     * Simulate a non-shooting foul and return the event
     */
    private function simulateNonShootingFoulWithEvent(Game $game, Team $team, Player $player, Player $defensivePlayer, int $quarter, int $timeRemaining): GameEvent
    {
        $description = $defensivePlayer->first_name . ' ' . $defensivePlayer->last_name . ' committed a foul on ' .
            $player->first_name . ' ' . $player->last_name;
            
        $defensiveTeam = $defensivePlayer->team;
        
        $event = $this->createGameEvent($game, $defensiveTeam, $defensivePlayer, 'foul', 0, $quarter, $timeRemaining, 
            $description, $game->home_team_score, $game->away_team_score, $player);
        
        $defTeamStats = $defensiveTeam->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        if (!isset($defTeamStats['fouls'])) {
            $defTeamStats['fouls'] = 0;
        }
        $defTeamStats['fouls']++;
        
        if ($defensiveTeam->id === $game->home_team_id) {
            $this->homeTeamStats = $defTeamStats;
        } else {
            $this->awayTeamStats = $defTeamStats;
        }
        
        return $event;
    }
    
    /**
     * Simulate a shot and return the events
     */
    private function simulateShotWithEvents(Game $game, Team $team, Player $player, int $quarter, int $timeRemaining): array
    {
        $events = [];
        $isThreePointer = (mt_rand(1, 100) / 100) <= self::PROBABILITY_THREE_POINTER;
        $isMade = false;
        
        if ($isThreePointer) {
            $isMade = (mt_rand(1, 100) / 100) <= self::PROBABILITY_THREE_POINTER_MADE;
            
            $playerStatsKey = $this->getPlayerStatsKey($player->id);
            $this->playerStats[$playerStatsKey]['three_pointers_attempted']++;
            
            if ($isMade) {
                $this->playerStats[$playerStatsKey]['three_pointers_made']++;
            }
        } 
        else {
            $isMade = (mt_rand(1, 100) / 100) <= self::PROBABILITY_FIELD_GOAL_MADE;
            
            $playerStatsKey = $this->getPlayerStatsKey($player->id);
            $this->playerStats[$playerStatsKey]['field_goals_attempted']++;
            
            if ($isMade) {
                $this->playerStats[$playerStatsKey]['field_goals_made']++;
            }
        }
        
        $assistPlayer = null;
        if ($isMade && (mt_rand(1, 100) / 100) <= self::PROBABILITY_ASSIST) {
            $teammatePlayers = $team->id === $game->home_team_id 
                ? $this->homeTeamActivePlayers 
                : $this->awayTeamActivePlayers;
            
            $teammatePlayers = array_filter($teammatePlayers, function($id) use ($player) {
                return $id !== $player->id;
            });
            
            if (!empty($teammatePlayers)) {
                $teammatePlayers = array_values($teammatePlayers);
                
                $teammateIndex = array_rand($teammatePlayers);
                $teammateId = $teammatePlayers[$teammateIndex];
                $assistPlayer = Player::find($teammateId);
                
                if (!$assistPlayer) {
                    Log::warning("Could not find assistant player with ID {$teammateId} for team {$team->name} in game {$game->id}");
                    $assistPlayer = null;
                } else {
                    $playerStatsKey = $this->getPlayerStatsKey($assistPlayer->id);
                    $this->playerStats[$playerStatsKey]['assists']++;
                }
            } else {
                Log::warning("No teammates available for assist in game {$game->id} for team {$team->name}.");
            }
        }
        
        if ($isMade) {
            $scoreValue = $isThreePointer ? 3 : 2;
 
            if ($team->id === $game->home_team_id) {
                $game->home_team_score += $scoreValue;
                if (!isset($this->homeTeamStats['points'])) {
                    $this->homeTeamStats['points'] = 0;
                }
                $this->homeTeamStats['points'] += $scoreValue;
            } else {
                $game->away_team_score += $scoreValue;
                if (!isset($this->awayTeamStats['points'])) {
                    $this->awayTeamStats['points'] = 0;
                }
                $this->awayTeamStats['points'] += $scoreValue;
            }
            $game->save();
            
            $playerStatsKey = $this->getPlayerStatsKey($player->id);
            $this->playerStats[$playerStatsKey]['points'] += $scoreValue;
            
            $eventType = $isThreePointer ? 'three_pointer' : 'field_goal';
            $description = $player->first_name . ' ' . $player->last_name . ' made a ' . 
                ($isThreePointer ? 'three-pointer' : 'field goal');
            
            if ($assistPlayer) {
                $description .= ' (assisted by ' . $assistPlayer->first_name . ' ' . $assistPlayer->last_name . ')';
            }
            
            $event = $this->createGameEvent($game, $team, $player, $eventType, $scoreValue, $quarter, $timeRemaining, 
                $description, $game->home_team_score, $game->away_team_score, $assistPlayer);
            $events[] = $event;
        } else {
            $eventType = $isThreePointer ? 'three_pointer' : 'field_goal';
            $description = $player->first_name . ' ' . $player->last_name . ' missed a ' . 
                ($isThreePointer ? 'three-pointer' : 'field goal');
            
            $event = $this->createGameEvent($game, $team, $player, $eventType, 0, $quarter, $timeRemaining, 
                $description, $game->home_team_score, $game->away_team_score);
            $events[] = $event;
        }
        
        $teamStats = $team->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        
        if ($isThreePointer) {
            if (!isset($teamStats['three_pointers_attempted'])) {
                $teamStats['three_pointers_attempted'] = 0;
            }
            $teamStats['three_pointers_attempted']++;
            
            if ($isMade) {
                if (!isset($teamStats['three_pointers_made'])) {
                    $teamStats['three_pointers_made'] = 0;
                }
                $teamStats['three_pointers_made']++;
            }
        } else {
            if (!isset($teamStats['field_goals_attempted'])) {
                $teamStats['field_goals_attempted'] = 0;
            }
            $teamStats['field_goals_attempted']++;
            
            if ($isMade) {
                if (!isset($teamStats['field_goals_made'])) {
                    $teamStats['field_goals_made'] = 0;
                }
                $teamStats['field_goals_made']++;
            }
        }
        
        if ($team->id === $game->home_team_id) {
            $this->homeTeamStats = $teamStats;
        } else {
            $this->awayTeamStats = $teamStats;
        }
        
        $this->updateTeamStats($game, $team->id === $game->home_team_id);
        
        if (mt_rand(1, 10) === 1) {
            $this->updatePlayerStats();
        }
        
        return $events;
    }
    
    /**
     * Format a game event for the real-time update
     */
    private function formatEventForUpdate(GameEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->event_type,
            'quarter' => $event->quarter,
            'time' => $event->quarter_time,
            'description' => $event->description,
            'home_score' => $event->home_score,
            'away_score' => $event->away_score,
            'player' => $event->player ? [
                'id' => $event->player->id,
                'name' => $event->player->first_name . ' ' . $event->player->last_name,
                'team_id' => $event->player->team_id
            ] : null,
            'team' => $event->team ? [
                'id' => $event->team->id,
                'name' => $event->team->name
            ] : null
        ];
    }
    
    /**
     * Start a new game simulation
     */
    public function startGame(Game $game): void
    {
        try {
            DB::beginTransaction();
            
            $game->status = 'in_progress';
            $game->started_at = now();
            $game->current_quarter = 1;
            $game->quarter_time_seconds = self::QUARTER_LENGTH_SECONDS;
            $game->home_team_score = 0;
            $game->away_team_score = 0;
            $game->save();
            
            $this->initializeTeamStats($game);
            
            $this->initializePlayerStats($game);
            
            $this->createGameEvent($game, null, null, 'game_start', 0, 1, self::QUARTER_LENGTH_SECONDS, 
                'Game started between ' . $game->homeTeam->name . ' and ' . $game->awayTeam->name, 0, 0);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error starting game: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Simulate the entire game
     */
    public function simulateGame(Game $game): void
    {
        try {
            $this->startGame($game);
            
            for ($quarter = 1; $quarter <= self::QUARTER_COUNT; $quarter++) {
                $this->simulateQuarter($game, $quarter);
                
                if ($quarter < self::QUARTER_COUNT) {
                    $game->current_quarter = $quarter + 1;
                    $game->quarter_time_seconds = self::QUARTER_LENGTH_SECONDS;
                    $game->save();
                    
                    $this->createGameEvent($game, null, null, 'quarter_start', 0, $quarter + 1, self::QUARTER_LENGTH_SECONDS, 
                        'Quarter ' . ($quarter + 1) . ' started', $game->home_team_score, $game->away_team_score);
                }
            }
            
            while ($game->home_team_score === $game->away_team_score) {
                $overtimeNumber = $game->current_quarter - self::QUARTER_COUNT + 1;
                
                $game->current_quarter = self::QUARTER_COUNT + $overtimeNumber;
                $game->quarter_time_seconds = self::OVERTIME_LENGTH_SECONDS;
                $game->save();
                
                $this->createGameEvent($game, null, null, 'quarter_start', 0, $game->current_quarter, self::OVERTIME_LENGTH_SECONDS, 
                    'Overtime ' . $overtimeNumber . ' started', $game->home_team_score, $game->away_team_score);
                
                $this->simulateQuarter($game, $game->current_quarter, self::OVERTIME_LENGTH_SECONDS);
            }
            
            $this->endGame($game);
            
        } catch (\Exception $e) {
            Log::error("Error simulating game: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Simulate a single quarter (or overtime)
     */
    private function simulateQuarter(Game $game, int $quarter, int $quarterLength = null): void
    {
        $quarterLength = $quarterLength ?? self::QUARTER_LENGTH_SECONDS;
        $timeRemaining = $quarterLength;
        
        while ($timeRemaining > 0) {
            $possessionTime = rand(10, 25);

            if ($possessionTime > $timeRemaining) {
                $possessionTime = $timeRemaining;
            }
            
            $timeRemaining -= $possessionTime;
            $game->quarter_time_seconds = $timeRemaining;
            $game->save();
            
            $this->simulatePossession($game, $quarter, $timeRemaining);
        }
        
        $this->createGameEvent($game, null, null, 'quarter_end', 0, $quarter, 0, 
            ($quarter <= 4 ? 'Quarter ' : 'Overtime ') . ($quarter <= 4 ? $quarter : $quarter - 4) . ' ended', 
            $game->home_team_score, $game->away_team_score);
            
        Log::info("Quarter {$quarter} ended. Score: {$game->homeTeam->name} {$game->home_team_score} - {$game->awayTeam->name} {$game->away_team_score}");
    }
    
    /**
     * Simulate a single possession
     */
    private function simulatePossession(Game $game, int $quarter, int $timeRemaining): void
    {
        $isHomePossession = (mt_rand(1, 100) / 100) <= self::PROBABILITY_HOME_ADVANTAGE;
        
        $offensiveTeam = $isHomePossession ? $game->homeTeam : $game->awayTeam;
        $defensiveTeam = $isHomePossession ? $game->awayTeam : $game->homeTeam;
        
        $activePlayers = $isHomePossession ? $this->homeTeamActivePlayers : $this->awayTeamActivePlayers;
        
        if (empty($activePlayers)) {
            Log::warning("No active players found for {$offensiveTeam->name} (Team ID: {$offensiveTeam->id}) in game {$game->id}. Re-initializing player stats.");
            $this->initializePlayerStats($game);

            $activePlayers = $isHomePossession ? $this->homeTeamActivePlayers : $this->awayTeamActivePlayers;
            
            if (empty($activePlayers)) {
                Log::error("Failed to initialize active players for {$offensiveTeam->name} in game {$game->id}. Team has " . 
                           $offensiveTeam->players()->count() . " players in database.");
                return;
            }
        }
        
        $playerIndex = array_rand($activePlayers);
        $playerId = $activePlayers[$playerIndex];
        $offensivePlayer = Player::find($playerId);
        
        if (!$offensivePlayer) {
            Log::error("Could not find player with ID {$playerId} for team {$offensiveTeam->name} in game {$game->id}");
            return;
        }
        
        if ((mt_rand(1, 100) / 100) <= self::PROBABILITY_TURNOVER) {
            $this->simulateTurnover($game, $offensiveTeam, $offensivePlayer, $quarter, $timeRemaining);
            return;
        }
        
        if ((mt_rand(1, 100) / 100) <= self::PROBABILITY_FOUL) {
            $defensiveActivePlayers = $isHomePossession ? $this->awayTeamActivePlayers : $this->homeTeamActivePlayers;

            if (empty($defensiveActivePlayers)) {
                Log::warning("No active defensive players found for {$defensiveTeam->name} (Team ID: {$defensiveTeam->id}) in game {$game->id}.");
                return;
            }
            
            $defPlayerIndex = array_rand($defensiveActivePlayers);
            $defPlayerId = $defensiveActivePlayers[$defPlayerIndex];
            $defensivePlayer = Player::find($defPlayerId);
            
            if (!$defensivePlayer) {
                Log::error("Could not find defensive player with ID {$defPlayerId} for team {$defensiveTeam->name} in game {$game->id}");
                return;
            }

            if ((mt_rand(1, 100) / 100) <= self::PROBABILITY_SHOOTING_FOUL) {
                $this->simulateShootingFoul($game, $offensiveTeam, $offensivePlayer, $defensivePlayer, $quarter, $timeRemaining);
            } else {
                $this->simulateNonShootingFoul($game, $offensiveTeam, $offensivePlayer, $defensivePlayer, $quarter, $timeRemaining);
            }
            return;
        }
        
        $this->simulateShot($game, $offensiveTeam, $offensivePlayer, $quarter, $timeRemaining);
    }
    
    /**
     * Simulate a shot
     */
    private function simulateShot(Game $game, Team $team, Player $player, int $quarter, int $timeRemaining): void
    {
        $isThreePointer = (mt_rand(1, 100) / 100) <= self::PROBABILITY_THREE_POINTER;
        $isMade = false;
        
        if ($isThreePointer) {
            $isMade = (mt_rand(1, 100) / 100) <= self::PROBABILITY_THREE_POINTER_MADE;
            
            $playerStatsKey = $this->getPlayerStatsKey($player->id);
            $this->playerStats[$playerStatsKey]['three_pointers_attempted']++;
            
            if ($isMade) {
                $this->playerStats[$playerStatsKey]['three_pointers_made']++;
            }
        } 
        else {
            $isMade = (mt_rand(1, 100) / 100) <= self::PROBABILITY_FIELD_GOAL_MADE;
            
            $playerStatsKey = $this->getPlayerStatsKey($player->id);
            $this->playerStats[$playerStatsKey]['field_goals_attempted']++;
            
            if ($isMade) {
                $this->playerStats[$playerStatsKey]['field_goals_made']++;
            }
        }
        
        $assistPlayer = null;
        if ($isMade && (mt_rand(1, 100) / 100) <= self::PROBABILITY_ASSIST) {
            $teammatePlayers = $team->id === $game->home_team_id 
                ? $this->homeTeamActivePlayers 
                : $this->awayTeamActivePlayers;
            
            $teammatePlayers = array_filter($teammatePlayers, function($id) use ($player) {
                return $id !== $player->id;
            });
            
            if (!empty($teammatePlayers)) {
                $teammatePlayers = array_values($teammatePlayers);
                
                $teammateIndex = array_rand($teammatePlayers);
                $teammateId = $teammatePlayers[$teammateIndex];
                $assistPlayer = Player::find($teammateId);
                
                if (!$assistPlayer) {
                    Log::warning("Could not find assistant player with ID {$teammateId} for team {$team->name} in game {$game->id}");
                    $assistPlayer = null;
                } else {
                    $playerStatsKey = $this->getPlayerStatsKey($assistPlayer->id);
                    $this->playerStats[$playerStatsKey]['assists']++;
                }
            } else {
                Log::warning("No teammates available for assist in game {$game->id} for team {$team->name}.");
            }
        }
        
        if ($isMade) {
            $scoreValue = $isThreePointer ? 3 : 2;
            
            if ($team->id === $game->home_team_id) {
                $game->home_team_score += $scoreValue;
                if (!isset($this->homeTeamStats['points'])) {
                    $this->homeTeamStats['points'] = 0;
                }
                $this->homeTeamStats['points'] += $scoreValue;
            } else {
                $game->away_team_score += $scoreValue;
                if (!isset($this->awayTeamStats['points'])) {
                    $this->awayTeamStats['points'] = 0;
                }
                $this->awayTeamStats['points'] += $scoreValue;
            }
            $game->save();
            
            $playerStatsKey = $this->getPlayerStatsKey($player->id);
            $this->playerStats[$playerStatsKey]['points'] += $scoreValue;
            
            $eventType = $isThreePointer ? 'three_pointer' : 'field_goal';
            $description = $player->first_name . ' ' . $player->last_name . ' made a ' . 
                ($isThreePointer ? 'three-pointer' : 'field goal');
            
            if ($assistPlayer) {
                $description .= ' (assisted by ' . $assistPlayer->first_name . ' ' . $assistPlayer->last_name . ')';
            }
            
            $this->createGameEvent($game, $team, $player, $eventType, $scoreValue, $quarter, $timeRemaining, 
                $description, $game->home_team_score, $game->away_team_score, $assistPlayer);
        } else {
            $eventType = $isThreePointer ? 'three_pointer' : 'field_goal';
            $description = $player->first_name . ' ' . $player->last_name . ' missed a ' . 
                ($isThreePointer ? 'three-pointer' : 'field goal');
            
            $this->createGameEvent($game, $team, $player, $eventType, 0, $quarter, $timeRemaining, 
                $description, $game->home_team_score, $game->away_team_score);
        }
        
        $teamStats = $team->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        
        if ($isThreePointer) {
            if (!isset($teamStats['three_pointers_attempted'])) {
                $teamStats['three_pointers_attempted'] = 0;
            }
            $teamStats['three_pointers_attempted']++;
            
            if ($isMade) {
                if (!isset($teamStats['three_pointers_made'])) {
                    $teamStats['three_pointers_made'] = 0;
                }
                $teamStats['three_pointers_made']++;
            }
        } else {
            if (!isset($teamStats['field_goals_attempted'])) {
                $teamStats['field_goals_attempted'] = 0;
            }
            $teamStats['field_goals_attempted']++;
            
            if ($isMade) {
                if (!isset($teamStats['field_goals_made'])) {
                    $teamStats['field_goals_made'] = 0;
                }
                $teamStats['field_goals_made']++;
            }
        }
        
        if ($team->id === $game->home_team_id) {
            $this->homeTeamStats = $teamStats;
        } else {
            $this->awayTeamStats = $teamStats;
        }
        
        $this->updateTeamStats($game, $team->id === $game->home_team_id);
        
        if (mt_rand(1, 10) === 1) {
            $this->updatePlayerStats();
        }
    }
    
    /**
     * Simulate a turnover
     */
    private function simulateTurnover(Game $game, Team $team, Player $player, int $quarter, int $timeRemaining): void
    {
        $description = $player->first_name . ' ' . $player->last_name . ' committed a turnover';
        
        $this->createGameEvent($game, $team, $player, 'turnover', 0, $quarter, $timeRemaining, 
            $description, $game->home_team_score, $game->away_team_score);
        
        $teamStats = $team->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        
        if (!isset($teamStats['turnovers'])) {
            $teamStats['turnovers'] = 0;
        }
        
        $teamStats['turnovers']++;
        
        if ($team->id === $game->home_team_id) {
            $this->homeTeamStats = $teamStats;
        } else {
            $this->awayTeamStats = $teamStats;
        }
        
        $this->updateTeamStats($game, $team->id === $game->home_team_id);
    }
    
    /**
     * Simulate a shooting foul
     */
    private function simulateShootingFoul(Game $game, Team $team, Player $player, Player $defensivePlayer, int $quarter, int $timeRemaining): void
    {
        $description = $defensivePlayer->first_name . ' ' . $defensivePlayer->last_name . ' committed a shooting foul on ' .
            $player->first_name . ' ' . $player->last_name;
            
        $defensiveTeam = $defensivePlayer->team;
        
        $this->createGameEvent($game, $defensiveTeam, $defensivePlayer, 'foul', 0, $quarter, $timeRemaining, 
            $description, $game->home_team_score, $game->away_team_score, $player);
            
        $freeThrowCount = (mt_rand(1, 100) / 100) <= self::PROBABILITY_THREE_POINTER ? 3 : 2;
        
        $madeCount = 0;
        for ($i = 0; $i < $freeThrowCount; $i++) {
            $isMade = (mt_rand(1, 100) / 100) <= self::PROBABILITY_FREE_THROW_MADE;
            
            if ($isMade) {
                $madeCount++;
                
                if ($team->id === $game->home_team_id) {
                    $game->home_team_score++;
                } else {
                    $game->away_team_score++;
                }
                
                $description = $player->first_name . ' ' . $player->last_name . ' made free throw ' . ($i + 1) . ' of ' . $freeThrowCount;
                $this->createGameEvent($game, $team, $player, 'free_throw', 1, $quarter, $timeRemaining, 
                    $description, $game->home_team_score, $game->away_team_score);
                    
                $key = $this->getPlayerStatsKey($player->id);
                $this->playerStats[$key]['points']++;
                $this->playerStats[$key]['free_throws_made']++;
                $this->playerStats[$key]['free_throws_attempted']++;
            } else {
                $description = $player->first_name . ' ' . $player->last_name . ' missed free throw ' . ($i + 1) . ' of ' . $freeThrowCount;
                $this->createGameEvent($game, $team, $player, 'free_throw', 0, $quarter, $timeRemaining, 
                    $description, $game->home_team_score, $game->away_team_score);
                    
                $key = $this->getPlayerStatsKey($player->id);
                $this->playerStats[$key]['free_throws_attempted']++;
            }
            
            $game->save();
        }
        
        $teamStats = $team->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        
        if (!isset($teamStats['free_throws_attempted'])) {
            $teamStats['free_throws_attempted'] = 0;
        }
        if (!isset($teamStats['free_throws_made'])) {
            $teamStats['free_throws_made'] = 0;
        }
        
        $teamStats['free_throws_attempted'] += $freeThrowCount;
        $teamStats['free_throws_made'] += $madeCount;
        
        if ($team->id === $game->home_team_id) {
            $this->homeTeamStats = $teamStats;
        } else {
            $this->awayTeamStats = $teamStats;
        }
        
        $this->updateTeamStats($game, $team->id === $game->home_team_id);
        
        $defTeamStats = $defensiveTeam->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        if (!isset($defTeamStats['fouls'])) {
            $defTeamStats['fouls'] = 0;
        }
        $defTeamStats['fouls']++;
        
        if ($defensiveTeam->id === $game->home_team_id) {
            $this->homeTeamStats = $defTeamStats;
        } else {
            $this->awayTeamStats = $defTeamStats;
        }
    }
    
    /**
     * Simulate a non-shooting foul
     */
    private function simulateNonShootingFoul(Game $game, Team $team, Player $player, Player $defensivePlayer, int $quarter, int $timeRemaining): void
    {
        $description = $defensivePlayer->first_name . ' ' . $defensivePlayer->last_name . ' committed a foul on ' .
            $player->first_name . ' ' . $player->last_name;
            
        $defensiveTeam = $defensivePlayer->team;
        
        $this->createGameEvent($game, $defensiveTeam, $defensivePlayer, 'foul', 0, $quarter, $timeRemaining, 
            $description, $game->home_team_score, $game->away_team_score, $player);
        
        $defTeamStats = $defensiveTeam->id === $game->home_team_id ? $this->homeTeamStats : $this->awayTeamStats;
        if (!isset($defTeamStats['fouls'])) {
            $defTeamStats['fouls'] = 0;
        }
        $defTeamStats['fouls']++;
        
        if ($defensiveTeam->id === $game->home_team_id) {
            $this->homeTeamStats = $defTeamStats;
        } else {
            $this->awayTeamStats = $defTeamStats;
        }
    }
    
    /**
     * Initialize team statistics for the game
     */
    private function initializeTeamStats(Game $game): void
    {
        $homeStats = GameStatistic::create([
            'game_id' => $game->id,
            'team_id' => $game->home_team_id,
            'is_home_team' => true,
            'q1_score' => 0,
            'q2_score' => 0,
            'q3_score' => 0,
            'q4_score' => 0,
            'ot_score' => 0,
            'assists' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_pointers_made' => 0,
            'three_pointers_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
            'attack_count' => 0,
        ]);
        
        $awayStats = GameStatistic::create([
            'game_id' => $game->id,
            'team_id' => $game->away_team_id,
            'is_home_team' => false,
            'q1_score' => 0,
            'q2_score' => 0,
            'q3_score' => 0,
            'q4_score' => 0,
            'ot_score' => 0,
            'assists' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_pointers_made' => 0,
            'three_pointers_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
            'attack_count' => 0,
        ]);
        
        $this->homeTeamStats = $homeStats->toArray();
        $this->awayTeamStats = $awayStats->toArray();
        
        $this->homeTeamStats['points'] = 0;
        $this->homeTeamStats['fouls'] = 0;
        $this->homeTeamStats['turnovers'] = 0;
        $this->awayTeamStats['points'] = 0;
        $this->awayTeamStats['fouls'] = 0;
        $this->awayTeamStats['turnovers'] = 0;
    }
    
    /**
     * Initialize player statistics and set up active players
     */
    private function initializePlayerStats(Game $game): void
    {
        $this->homeTeamActivePlayers = [];
        $this->awayTeamActivePlayers = [];
        
        $homePlayers = $game->homeTeam->players()->inRandomOrder()->limit(5)->get();
        $awayPlayers = $game->awayTeam->players()->inRandomOrder()->limit(5)->get();
        
        foreach ($homePlayers as $player) {
            $existingStat = PlayerStatistic::where('game_id', $game->id)
                ->where('player_id', $player->id)
                ->first();
            
            if ($existingStat) {
                $this->playerStats[$this->getPlayerStatsKey($player->id)] = $existingStat->toArray();
            } else {
                $playerStat = PlayerStatistic::create([
                    'game_id' => $game->id,
                    'player_id' => $player->id,
                    'team_id' => $player->team_id,
                    'seconds_played' => 0,
                    'points' => 0,
                    'assists' => 0,
                    'field_goals_made' => 0,
                    'field_goals_attempted' => 0,
                    'three_pointers_made' => 0,
                    'three_pointers_attempted' => 0,
                    'free_throws_made' => 0,
                    'free_throws_attempted' => 0,
                ]);
                
                $this->playerStats[$this->getPlayerStatsKey($player->id)] = $playerStat->toArray();
            }
            
            $this->homeTeamActivePlayers[] = $player->id;
        }
        
        foreach ($awayPlayers as $player) {
            $existingStat = PlayerStatistic::where('game_id', $game->id)
                ->where('player_id', $player->id)
                ->first();
            
            if ($existingStat) {
                $this->playerStats[$this->getPlayerStatsKey($player->id)] = $existingStat->toArray();
            } else {
                $playerStat = PlayerStatistic::create([
                    'game_id' => $game->id,
                    'player_id' => $player->id,
                    'team_id' => $player->team_id,
                    'seconds_played' => 0,
                    'points' => 0,
                    'assists' => 0,
                    'field_goals_made' => 0,
                    'field_goals_attempted' => 0,
                    'three_pointers_made' => 0,
                    'three_pointers_attempted' => 0,
                    'free_throws_made' => 0,
                    'free_throws_attempted' => 0,
                ]);
                
                $this->playerStats[$this->getPlayerStatsKey($player->id)] = $playerStat->toArray();
            }
            
            $this->awayTeamActivePlayers[] = $player->id;
        }
        
        Log::info("Initialized player stats. Home team active players: " . count($this->homeTeamActivePlayers) . 
                  ", Away team active players: " . count($this->awayTeamActivePlayers));
    }
    
    /**
     * Create a game event
     */
    private function createGameEvent(
        Game $game, 
        ?Team $team, 
        ?Player $player, 
        string $eventType, 
        int $scoreValue, 
        int $quarter, 
        int $quarterTime, 
        string $description,
        int $homeScore,
        int $awayScore,
        ?Player $secondaryPlayer = null
    ): GameEvent {
        $eventData = [
            'game_id' => $game->id,
            'event_type' => $eventType,
            'score_value' => $scoreValue,
            'quarter' => $quarter,
            'quarter_time' => $quarterTime,
            'description' => $description,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];
        
        if ($team) {
            $eventData['team_id'] = $team->id;
        }
        
        if ($player) {
            $eventData['player_id'] = $player->id;
        }
        
        if ($secondaryPlayer) {
            $eventData['secondary_player_id'] = $secondaryPlayer->id;
        }
        
        return GameEvent::create($eventData);
    }
    
    /**
     * Get a unique key for player stats cache
     */
    private function getPlayerStatsKey(int $playerId): string
    {
        return 'player_' . $playerId;
    }
    
    /**
     * Update team statistics in the database
     */
    private function updateTeamStats(Game $game, bool $isHomeTeam): void
    {
        $stats = $isHomeTeam ? $this->homeTeamStats : $this->awayTeamStats;
        
        $quarterField = 'q' . $game->current_quarter . '_score';
        
        if ($game->current_quarter > 4) {
            $quarterField = 'ot_score';
        }
  
        if (!isset($stats['points'])) {
            $stats['points'] = 0;
            
            if ($isHomeTeam) {
                $this->homeTeamStats['points'] = 0;
            } else {
                $this->awayTeamStats['points'] = 0;
            }
        }
        
        GameStatistic::where('game_id', $game->id)
            ->where('is_home_team', $isHomeTeam)
            ->update([
                $quarterField => $stats['points'], // Use the in-memory points for quarter scores
                'assists' => $stats['assists'] ?? 0,
                'field_goals_made' => $stats['field_goals_made'] ?? 0,
                'field_goals_attempted' => $stats['field_goals_attempted'] ?? 0,
                'three_pointers_made' => $stats['three_pointers_made'] ?? 0,
                'three_pointers_attempted' => $stats['three_pointers_attempted'] ?? 0,
                'free_throws_made' => $stats['free_throws_made'] ?? 0,
                'free_throws_attempted' => $stats['free_throws_attempted'] ?? 0,
                'attack_count' => ($stats['field_goals_attempted'] ?? 0) + ($stats['free_throws_attempted'] ?? 0),
            ]);
    }
    
    /**
     * Finalize team stats by calculating percentages
     */
    private function finalizeTeamStats(Game $game): void
    {
        $homeStats = GameStatistic::where('game_id', $game->id)
            ->where('is_home_team', true)
            ->first();
            
        if ($homeStats->field_goals_attempted > 0) {
            $homeStats->field_goal_percentage = round(($homeStats->field_goals_made / $homeStats->field_goals_attempted) * 100, 1);
        }
        
        if ($homeStats->three_pointers_attempted > 0) {
            $homeStats->three_point_percentage = round(($homeStats->three_pointers_made / $homeStats->three_pointers_attempted) * 100, 1);
        }
        
        if ($homeStats->free_throws_attempted > 0) {
            $homeStats->free_throw_percentage = round(($homeStats->free_throws_made / $homeStats->free_throws_attempted) * 100, 1);
        }
        
        $homeStats->save();
        
        $awayStats = GameStatistic::where('game_id', $game->id)
            ->where('is_home_team', false)
            ->first();
            
        if ($awayStats->field_goals_attempted > 0) {
            $awayStats->field_goal_percentage = round(($awayStats->field_goals_made / $awayStats->field_goals_attempted) * 100, 1);
        }
        
        if ($awayStats->three_pointers_attempted > 0) {
            $awayStats->three_point_percentage = round(($awayStats->three_pointers_made / $awayStats->three_pointers_attempted) * 100, 1);
        }
        
        if ($awayStats->free_throws_attempted > 0) {
            $awayStats->free_throw_percentage = round(($awayStats->free_throws_made / $awayStats->free_throws_attempted) * 100, 1);
        }
        
        $awayStats->save();
    }
    
    /**
     * Update all player statistics in the database
     */
    private function updatePlayerStats(): void
    {
        foreach ($this->playerStats as $key => $stats) {
            $playerId = str_replace('player_', '', $key);
            
            PlayerStatistic::where('game_id', $stats['game_id'])
                ->where('player_id', $playerId)
                ->update([
                    'points' => $stats['points'],
                    'assists' => $stats['assists'],
                    'field_goals_made' => $stats['field_goals_made'],
                    'field_goals_attempted' => $stats['field_goals_attempted'],
                    'three_pointers_made' => $stats['three_pointers_made'],
                    'three_pointers_attempted' => $stats['three_pointers_attempted'],
                    'free_throws_made' => $stats['free_throws_made'],
                    'free_throws_attempted' => $stats['free_throws_attempted'],
                ]);
                
            $playerStat = PlayerStatistic::where('game_id', $stats['game_id'])
                ->where('player_id', $playerId)
                ->first();
                
            $playerStat->updatePercentages();
        }
    }

    /**
     * Get a scheduled game by ID
     * 
     * @param int $gameId The ID of the game to retrieve
     * @return Game|null The game if found and scheduled, null otherwise
     */
    public function getScheduledGameById(int $gameId): ?Game
    {
        $game = Game::where('id', $gameId)
            ->where('status', 'scheduled')
            ->first();
            
        return $game;
    }
} 