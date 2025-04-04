<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GameResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $game;
    protected $homeTeam;
    protected $awayTeam;

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
            'status' => 'scheduled',
            'scheduled_at' => '2023-04-01 12:00:00',
            'home_team_score' => 100,
            'away_team_score' => 95,
            'current_quarter' => 4,
            'quarter_time_seconds' => 0,
        ]);

        $this->game->load(['homeTeam', 'awayTeam']);
    }

    /**
     * Test that the resource contains all required fields.
     */
    public function test_resource_contains_required_fields(): void
    {
        $resource = new GameResource($this->game);
        $resourceArray = $resource->toArray(request());

        $this->assertArrayHasKey('id', $resourceArray);
        $this->assertArrayHasKey('status', $resourceArray);
        $this->assertArrayHasKey('date', $resourceArray);
        $this->assertArrayHasKey('home_team', $resourceArray);
        $this->assertArrayHasKey('away_team', $resourceArray);
        $this->assertArrayHasKey('current_quarter', $resourceArray);
        $this->assertArrayHasKey('quarter_time_seconds', $resourceArray);
        $this->assertArrayHasKey('started_at', $resourceArray);
        $this->assertArrayHasKey('ended_at', $resourceArray);
    }

    /**
     * Test that the nested home team data is correct.
     */
    public function test_home_team_data_is_correct(): void
    {
        $resource = new GameResource($this->game);
        $resourceArray = $resource->toArray(request());

        $this->assertArrayHasKey('id', $resourceArray['home_team']);
        $this->assertArrayHasKey('name', $resourceArray['home_team']);
        $this->assertArrayHasKey('score', $resourceArray['home_team']);
        
        $this->assertEquals($this->homeTeam->id, $resourceArray['home_team']['id']);
        $this->assertEquals($this->homeTeam->name, $resourceArray['home_team']['name']);
        $this->assertEquals($this->game->home_team_score, $resourceArray['home_team']['score']);
    }

    /**
     * Test that the nested away team data is correct.
     */
    public function test_away_team_data_is_correct(): void
    {
        $resource = new GameResource($this->game);
        $resourceArray = $resource->toArray(request());

        $this->assertArrayHasKey('id', $resourceArray['away_team']);
        $this->assertArrayHasKey('name', $resourceArray['away_team']);
        $this->assertArrayHasKey('score', $resourceArray['away_team']);
        
        $this->assertEquals($this->awayTeam->id, $resourceArray['away_team']['id']);
        $this->assertEquals($this->awayTeam->name, $resourceArray['away_team']['name']);
        $this->assertEquals($this->game->away_team_score, $resourceArray['away_team']['score']);
    }

    /**
     * Test that the game status is correctly included.
     */
    public function test_game_status_is_included(): void
    {
        $resource = new GameResource($this->game);
        $resourceArray = $resource->toArray(request());

        $this->assertEquals($this->game->status, $resourceArray['status']);
    }

    /**
     * Test that the game date is correctly included.
     */
    public function test_game_date_is_included(): void
    {
        $resource = new GameResource($this->game);
        $resourceArray = $resource->toArray(request());

        $this->assertEquals('2023-04-01', $resourceArray['date']);
    }
}
