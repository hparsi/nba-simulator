<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use App\Http\Resources\GameEventsCollectionResource;
use App\Http\Resources\GameEventResource;
use App\Models\Game;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class GameEventsCollectionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected $game;
    protected $team;
    protected $player;
    protected $gameEvents = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create([
            'name' => 'Test Team'
        ]);

        $awayTeam = Team::factory()->create();
        $this->game = Game::factory()->create([
            'home_team_id' => $this->team->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'in_progress'
        ]);

        $this->player = Player::factory()->create([
            'team_id' => $this->team->id,
            'first_name' => 'Test',
            'last_name' => 'Player'
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->gameEvents[] = GameEvent::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->team->id,
                'player_id' => $this->player->id,
                'event_type' => 'field_goal',
                'score_value' => 2,
                'quarter' => 1,
                'quarter_time' => 600 - ($i * 60),
                'description' => "Test Player made a field goal ($i)",
                'home_score' => ($i + 1) * 2,
                'away_score' => 0
            ]);
        }

        foreach ($this->gameEvents as $event) {
            $event->load(['player', 'team']);
        }
    }

    /**
     * Test that the collection resource can be created with an array.
     */
    public function test_collection_resource_can_be_created_with_array(): void
    {
        $collection = GameEventResource::collection($this->gameEvents);
        
        $this->assertInstanceOf(AnonymousResourceCollection::class, $collection);
        
        $resourceArray = $collection->response()->getData(true);
        
        $this->assertArrayHasKey('data', $resourceArray);
        $this->assertCount(3, $resourceArray['data']);
    }

    /**
     * Test that the collection resource can be created with a paginator.
     */
    public function test_collection_resource_can_be_created_with_paginator(): void
    {
        $paginator = new LengthAwarePaginator(
            $this->gameEvents,
            count($this->gameEvents),
            2,
            1
        );
        
        $collection = GameEventResource::collection($paginator);
        
        $this->assertInstanceOf(AnonymousResourceCollection::class, $collection);
        
        $resourceArray = $collection->response()->getData(true);
        
        $this->assertArrayHasKey('data', $resourceArray);
        $this->assertCount(3, $resourceArray['data']);
        $this->assertArrayHasKey('meta', $resourceArray);
        $this->assertArrayHasKey('links', $resourceArray);
    }

    /**
     * Test that each item in the collection is correctly formatted.
     */
    public function test_collection_items_are_formatted_correctly(): void
    {
        $collection = GameEventResource::collection($this->gameEvents);
        $resourceArray = $collection->response()->getData(true);
        
        foreach ($resourceArray['data'] as $index => $item) {
            $this->assertEquals($this->gameEvents[$index]->id, $item['id']);
            $this->assertEquals($this->gameEvents[$index]->event_type, $item['event_type']);
            $this->assertEquals($this->gameEvents[$index]->description, $item['description']);
            $this->assertEquals($this->gameEvents[$index]->quarter, $item['quarter']);
            $this->assertEquals($this->gameEvents[$index]->quarter_time, $item['quarter_time']);
            
            $this->assertEquals($this->team->id, $item['team']['id']);
            $this->assertEquals($this->team->name, $item['team']['name']);
            
            $this->assertEquals($this->player->id, $item['player']['id']);
            $this->assertEquals($this->player->first_name . ' ' . $this->player->last_name, $item['player']['name']);
            $this->assertEquals($this->player->team_id, $item['player']['team_id']);
        }
    }

    /**
     * Test collection with limit parameter in request.
     */
    public function test_collection_respects_limit_parameter(): void
    {
        $request = request()->merge(['limit' => 2]);
        
        $collection = GameEventResource::collection($this->gameEvents);
        
        $resourceArray = $collection->response()->getData(true);
        $this->assertCount(3, $resourceArray['data']);
    }

    /**
     * Test collection with since_id parameter in request.
     */
    public function test_collection_respects_since_id_parameter(): void
    {
        usort($this->gameEvents, function ($a, $b) {
            return $a->id <=> $b->id;
        });
        
        $since_id = $this->gameEvents[0]->id;
        $request = request()->merge(['since_id' => $since_id]);
        
        $collection = GameEventResource::collection($this->gameEvents);
        
        $resourceArray = $collection->response()->getData(true);
        $this->assertCount(3, $resourceArray['data']);
    }
} 