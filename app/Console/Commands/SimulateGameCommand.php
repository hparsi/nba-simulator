<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Team;
use App\Services\GameSimulationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SimulateGameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nba:simulate-game
                            {--home= : Home team ID}
                            {--away= : Away team ID}
                            {--scheduled= : Scheduled time (Y-m-d H:i:s)}
                            {--game= : Existing game ID to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an NBA game between two teams';

    /**
     * The game simulation service.
     *
     * @var GameSimulationService
     */
    protected $simulationService;

    /**
     * Create a new command instance.
     *
     * @param GameSimulationService $simulationService
     */
    public function __construct(GameSimulationService $simulationService)
    {
        parent::__construct();
        
        $this->simulationService = $simulationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $game = $this->getOrCreateGame();
        
        if (!$game) {
            $this->error('Failed to create or find a game to simulate.');
            return 1;
        }
        
        $this->info('Starting game simulation: ' . $game->homeTeam->name . ' vs ' . $game->awayTeam->name);
        
        try {
            $this->simulationService->simulateGame($game);
            
            $this->info('Game completed!');
            $this->info('Final score: ' . $game->homeTeam->name . ' ' . $game->home_team_score . ' - ' . 
                        $game->awayTeam->name . ' ' . $game->away_team_score);
            
            $this->info('Game statistics:');
            $this->displayGameStats($game);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error simulating game: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
    
    /**
     * Get an existing game or create a new one
     */
    private function getOrCreateGame(): ?Game
    {
        if ($gameId = $this->option('game')) {
            $game = Game::find($gameId);
            
            if (!$game) {
                $this->error('Game with ID ' . $gameId . ' not found.');
                return null;
            }
            
            if ($game->status !== 'scheduled') {
                $this->error('Game is already ' . $game->status . '. Only scheduled games can be simulated.');
                return null;
            }
            
            return $game;
        }
        
        $homeTeamId = $this->option('home');
        $awayTeamId = $this->option('away');
        
        if (!$homeTeamId || !$awayTeamId) {
            $homeTeamId = $this->getTeamSelection('Select home team:');
            $awayTeamId = $this->getTeamSelection('Select away team:', [$homeTeamId]);
        }
        
        $homeTeam = Team::find($homeTeamId);
        $awayTeam = Team::find($awayTeamId);
        
        if (!$homeTeam || !$awayTeam) {
            $this->error('One or both teams not found.');
            return null;
        }
        
        $currentYear = date('Y');
        $season = \App\Models\Season::firstOrCreate(
            [
                'year_start' => $currentYear - 1,
                'year_end' => $currentYear
            ],
            [
                'name' => ($currentYear - 1) . '-' . $currentYear . ' NBA Season',
                'start_date' => ($currentYear - 1) . '-10-24', 
                'end_date' => $currentYear . '-06-15',
                'is_active' => true
            ]
        );
        
        $scheduledAt = $this->option('scheduled') 
            ? Carbon::createFromFormat('Y-m-d H:i:s', $this->option('scheduled'))
            : now();
        
        return Game::create([
            'season_id' => $season->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);
    }
    
    /**
     * Display a list of teams and let the user select one
     */
    private function getTeamSelection(string $message, array $excludeIds = []): int
    {
        $this->info($message);
        
        $teams = Team::orderBy('name')->get();
        $choices = [];
        
        foreach ($teams as $team) {
            if (!in_array($team->id, $excludeIds)) {
                $choices[$team->id] = $team->name;
            }
        }
        
        return $this->choice($message, $choices);
    }
    
    /**
     * Display game statistics
     */
    private function displayGameStats(Game $game): void
    {
        $homeStats = $game->homeTeamStats;
        $awayStats = $game->awayTeamStats;
        
        $this->table(
            ['Stat', $game->homeTeam->name, $game->awayTeam->name],
            [
                ['Points', $homeStats->points, $awayStats->points],
                ['FG', $homeStats->field_goals_made . '/' . $homeStats->field_goals_attempted . ' (' . $homeStats->field_goal_percentage . '%)', 
                      $awayStats->field_goals_made . '/' . $awayStats->field_goals_attempted . ' (' . $awayStats->field_goal_percentage . '%)'],
                ['3PT', $homeStats->three_pointers_made . '/' . $homeStats->three_pointers_attempted . ' (' . $homeStats->three_point_percentage . '%)', 
                       $awayStats->three_pointers_made . '/' . $awayStats->three_pointers_attempted . ' (' . $awayStats->three_point_percentage . '%)'],
                ['FT', $homeStats->free_throws_made . '/' . $homeStats->free_throws_attempted . ' (' . $homeStats->free_throw_percentage . '%)', 
                      $awayStats->free_throws_made . '/' . $awayStats->free_throws_attempted . ' (' . $awayStats->free_throw_percentage . '%)'],
                ['Assists', $homeStats->assists, $awayStats->assists],
                ['Fouls', $homeStats->fouls, $awayStats->fouls],
                ['Turnovers', $homeStats->turnovers, $awayStats->turnovers],
            ]
        );
        
        $this->info('Top Performers:');
        
        $topScorers = $game->playerStatistics()
            ->orderBy('points', 'desc')
            ->limit(5)
            ->get();
            
        $scorerRows = [];
        foreach ($topScorers as $stat) {
            $scorerRows[] = [
                $stat->player->full_name . ' (' . ($stat->team_id === $game->home_team_id ? 'Home' : 'Away') . ')',
                $stat->points . ' PTS',
                $stat->assists . ' AST',
                $stat->field_goals_made . '/' . $stat->field_goals_attempted . ' FG',
                $stat->three_pointers_made . '/' . $stat->three_pointers_attempted . ' 3PT',
            ];
        }
        
        $this->table(
            ['Player', 'Points', 'Assists', 'FG', '3PT'],
            $scorerRows
        );
    }
} 