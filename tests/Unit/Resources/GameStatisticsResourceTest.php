<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use App\Http\Resources\GameStatisticsResource;
use App\Models\Game;
use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GameStatisticsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $game;
    protected $homeTeam;
    protected $awayTeam;
    protected $homePlayers = [];
    protected $awayPlayers = [];
    protected $playerStats = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->homeTeam = Team::factory()->create([
            'name' => 'Home Team'
        ]);
        
        $this->awayTeam = Team::factory()->create([
            'name' => 'Away Team'
        ]);

        $this->game = Game::factory()->create([
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => 'in_progress'
        ]);

        for ($i = 0; $i < 3; $i++) {
            $player = Player::factory()->create([
                'team_id' => $this->homeTeam->id,
                'first_name' => "Home",
                'last_name' => "Player $i"
            ]);
            $this->homePlayers[] = $player;
            
            $stats = PlayerStatistic::factory()->create([
                'game_id' => $this->game->id,
                'player_id' => $player->id,
                'points' => 10 + $i,
                'assists' => 5 + $i,
                'field_goals_attempted' => 10 + $i,
                'field_goals_made' => 5 + $i,
                'three_pointers_attempted' => 5 + $i,
                'three_pointers_made' => 2 + $i
            ]);
            $this->playerStats[$player->id] = $stats;
        }

        for ($i = 0; $i < 2; $i++) {
            $player = Player::factory()->create([
                'team_id' => $this->awayTeam->id,
                'first_name' => "Away",
                'last_name' => "Player $i"
            ]);
            $this->awayPlayers[] = $player;
            
            $stats = PlayerStatistic::factory()->create([
                'game_id' => $this->game->id,
                'player_id' => $player->id,
                'points' => 8 + $i,
                'assists' => 4 + $i,
                'field_goals_attempted' => 8 + $i,
                'field_goals_made' => 4 + $i,
                'three_pointers_attempted' => 4 + $i,
                'three_pointers_made' => 1 + $i
            ]);
            $this->playerStats[$player->id] = $stats;
        }

        $this->game->load([
            'homeTeam', 
            'awayTeam', 
            'playerStatistics.player'
        ]);
    }

    /**
     * Test that the resource contains all required fields.
     */
    public function test_resource_contains_required_fields(): void
    {
        $resource = new GameStatisticsResource($this->game);
        $resourceArray = $resource->toArray(request());

        $this->assertArrayHasKey('home_team', $resourceArray);
        $this->assertArrayHasKey('away_team', $resourceArray);
        
        $this->assertArrayHasKey('players', $resourceArray['home_team']);
        $this->assertArrayHasKey('totals', $resourceArray['home_team']);
        
        $this->assertArrayHasKey('players', $resourceArray['away_team']);
        $this->assertArrayHasKey('totals', $resourceArray['away_team']);
    }

    /**
     * Test that the home team players data is correct.
     */
    public function test_home_team_players_data_is_correct(): void
    {
        $resource = new GameStatisticsResource($this->game);
        $resourceArray = $resource->toArray(request());
        
        $this->assertCount(3, $resourceArray['home_team']['players']);
        
        foreach ($resourceArray['home_team']['players'] as $playerStats) {
            $playerId = $playerStats['id'];
            $player = Player::find($playerId);
            $stats = $this->playerStats[$playerId];
            
            $this->assertEquals($player->id, $playerStats['id']);
            $this->assertEquals($player->first_name . ' ' . $player->last_name, $playerStats['name']);
            $this->assertEquals($stats->points, $playerStats['points']);
            $this->assertEquals($stats->assists, $playerStats['assists']);
            $this->assertEquals($stats->field_goals_attempted, $playerStats['field_goals_attempted']);
            $this->assertEquals($stats->field_goals_made, $playerStats['field_goals_made']);
            $this->assertEquals($stats->three_pointers_attempted, $playerStats['three_pointers_attempted']);
            $this->assertEquals($stats->three_pointers_made, $playerStats['three_pointers_made']);
        }
    }

    /**
     * Test that the away team players data is correct.
     */
    public function test_away_team_players_data_is_correct(): void
    {
        $resource = new GameStatisticsResource($this->game);
        $resourceArray = $resource->toArray(request());
        
        $this->assertCount(2, $resourceArray['away_team']['players']);
        
        foreach ($resourceArray['away_team']['players'] as $playerStats) {
            $playerId = $playerStats['id'];
            $player = Player::find($playerId);
            $stats = $this->playerStats[$playerId];
            
            $this->assertEquals($player->id, $playerStats['id']);
            $this->assertEquals($player->first_name . ' ' . $player->last_name, $playerStats['name']);
            $this->assertEquals($stats->points, $playerStats['points']);
            $this->assertEquals($stats->assists, $playerStats['assists']);
            $this->assertEquals($stats->field_goals_attempted, $playerStats['field_goals_attempted']);
            $this->assertEquals($stats->field_goals_made, $playerStats['field_goals_made']);
            $this->assertEquals($stats->three_pointers_attempted, $playerStats['three_pointers_attempted']);
            $this->assertEquals($stats->three_pointers_made, $playerStats['three_pointers_made']);
        }
    }

    /**
     * Test that team totals are calculated correctly.
     */
    public function test_team_totals_are_calculated_correctly(): void
    {
        $resource = new GameStatisticsResource($this->game);
        $resourceArray = $resource->toArray(request());
        
        $expectedHomeTotals = [
            'points' => 0,
            'assists' => 0,
            'field_goals_attempted' => 0,
            'field_goals_made' => 0,
            'three_pointers_attempted' => 0,
            'three_pointers_made' => 0
        ];
        
        foreach ($this->homePlayers as $player) {
            $stats = $this->playerStats[$player->id];
            $expectedHomeTotals['points'] += $stats->points;
            $expectedHomeTotals['assists'] += $stats->assists;
            $expectedHomeTotals['field_goals_attempted'] += $stats->field_goals_attempted;
            $expectedHomeTotals['field_goals_made'] += $stats->field_goals_made;
            $expectedHomeTotals['three_pointers_attempted'] += $stats->three_pointers_attempted;
            $expectedHomeTotals['three_pointers_made'] += $stats->three_pointers_made;
        }
        
        $expectedAwayTotals = [
            'points' => 0,
            'assists' => 0,
            'field_goals_attempted' => 0,
            'field_goals_made' => 0,
            'three_pointers_attempted' => 0,
            'three_pointers_made' => 0
        ];
        
        foreach ($this->awayPlayers as $player) {
            $stats = $this->playerStats[$player->id];
            $expectedAwayTotals['points'] += $stats->points;
            $expectedAwayTotals['assists'] += $stats->assists;
            $expectedAwayTotals['field_goals_attempted'] += $stats->field_goals_attempted;
            $expectedAwayTotals['field_goals_made'] += $stats->field_goals_made;
            $expectedAwayTotals['three_pointers_attempted'] += $stats->three_pointers_attempted;
            $expectedAwayTotals['three_pointers_made'] += $stats->three_pointers_made;
        }
        
        foreach ($expectedHomeTotals as $key => $value) {
            $this->assertEquals($value, $resourceArray['home_team']['totals'][$key], "Home team total for '$key' is incorrect");
        }
        
        foreach ($expectedAwayTotals as $key => $value) {
            $this->assertEquals($value, $resourceArray['away_team']['totals'][$key], "Away team total for '$key' is incorrect");
        }
    }

    /**
     * Test that player statistics are sorted by points.
     */
    public function test_player_statistics_are_sorted_by_points(): void
    {
        $resource = new GameStatisticsResource($this->game);
        $resourceArray = $resource->toArray(request());
        
        $homePoints = array_map(function ($player) {
            return $player['points'];
        }, $resourceArray['home_team']['players']);
        
        $this->assertEquals($homePoints, array_values(rsort($homePoints) ? $homePoints : []));
        
        $awayPoints = array_map(function ($player) {
            return $player['points'];
        }, $resourceArray['away_team']['players']);
        
        $this->assertEquals($awayPoints, array_values(rsort($awayPoints) ? $awayPoints : []));
    }
} 