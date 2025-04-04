<?php

namespace App\Services;

use App\Repositories\Interfaces\GameRepositoryInterface;
use Illuminate\Support\Collection;

class GameService
{
    protected $gameRepository;
    
    public function __construct(GameRepositoryInterface $gameRepository)
    {
        $this->gameRepository = $gameRepository;
    }
    
    public function getGames(array $params = [])
    {
        $filters = [];
        $relations = ['homeTeam', 'awayTeam'];
        
        if (isset($params['ids'])) {
            $filters['ids'] = explode(',', $params['ids']);
        }
        
        if (isset($params['status'])) {
            $filters['status'] = $params['status'];
        }
        
        if (isset($params['with_events']) && $params['with_events']) {
            $relations[] = 'events';
        }
        
        return $this->gameRepository->getAllGames($filters, $relations);
    }
    
    public function getGameById(int $id)
    {
        $relations = [
            'homeTeam', 
            'awayTeam',
            'events' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(20);
            },
            'playerStatistics' => function ($query) {
                $query->with('player');
            },
            'gameStatistics'
        ];
        
        return $this->gameRepository->findById($id, $relations);
    }
    
    public function getGameEvents(int $id, int $limit = 20, int $sinceId = 0)
    {
        return $this->gameRepository->getGameEvents($id, $limit, $sinceId);
    }
    
    public function getGameStatistics(int $id)
    {
        return $this->gameRepository->getGameStatistics($id);
    }
    
    public function scheduleNextWeekGames(array $playedMatchups = [])
    {
        $currentSeason = $this->gameRepository->getActiveSeason();
        
        if (!$currentSeason) {
            throw new \Exception('No active season found');
        }
   
        $teams = $this->gameRepository->getAllTeams();
        
        if ($teams->count() < 2) {
            throw new \Exception('Not enough teams to schedule games');
        }
        
        $recentMatchups = collect();
        foreach ($playedMatchups as $matchup) {
            $recentMatchups->push([
                $matchup['home_team_id'], 
                $matchup['away_team_id']
            ]);
            
            $recentMatchups->push([
                $matchup['away_team_id'], 
                $matchup['home_team_id']
            ]);
        }
        
        $existingGames = $this->gameRepository->getExistingGames($currentSeason->id);
        
        foreach ($existingGames as $game) {
            $recentMatchups->push([
                $game->home_team_id, 
                $game->away_team_id
            ]);
            
            $recentMatchups->push([
                $game->away_team_id, 
                $game->home_team_id
            ]);
        }
        
        $gamesPerWeek = floor($teams->count() / 2);
        
        $shuffledTeams = $teams->shuffle();
        
        $newMatchups = $this->createNewMatchups($shuffledTeams, $recentMatchups, $gamesPerWeek);
        
        $nextWeekDate = now()->addWeek();
        
        $createdGames = [];
        foreach ($newMatchups as $matchup) {
            $game = $this->gameRepository->createGame([
                'season_id' => $currentSeason->id,
                'home_team_id' => $matchup['home_team_id'],
                'away_team_id' => $matchup['away_team_id'],
                'scheduled_at' => $nextWeekDate,
                'home_team_score' => 0,
                'away_team_score' => 0,
                'status' => 'scheduled',
                'current_quarter' => 0,
                'quarter_time_seconds' => 720, // 12 minutes
            ]);
            
            $createdGames[] = $game;
        }
        
        return [
            'games' => $createdGames
        ];
    }
    
    private function createNewMatchups(Collection $shuffledTeams, Collection $recentMatchups, int $gamesPerWeek)
    {
        $newMatchups = [];
        $usedTeamIds = [];
        
        foreach ($shuffledTeams as $homeTeam) {
            if (in_array($homeTeam->id, $usedTeamIds)) {
                continue;
            }
            
            foreach ($shuffledTeams as $awayTeam) {
                if ($homeTeam->id === $awayTeam->id || in_array($awayTeam->id, $usedTeamIds)) {
                    continue;
                }
                
                $hasRecentMatchup = $recentMatchups->contains(function ($matchup) use ($homeTeam, $awayTeam) {
                    return ($matchup[0] == $homeTeam->id && $matchup[1] == $awayTeam->id) ||
                           ($matchup[0] == $awayTeam->id && $matchup[1] == $homeTeam->id);
                });
                
                if (!$hasRecentMatchup) {
                    $newMatchups[] = [
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id
                    ];
                    
                    $usedTeamIds[] = $homeTeam->id;
                    $usedTeamIds[] = $awayTeam->id;
                    
                    break;
                }
            }
            
            if (count($newMatchups) >= $gamesPerWeek) {
                break;
            }
        }
        
        if (count($newMatchups) < $gamesPerWeek) {
            foreach ($shuffledTeams as $homeTeam) {
                if (in_array($homeTeam->id, $usedTeamIds)) {
                    continue;
                }
                
                foreach ($shuffledTeams as $awayTeam) {
                    if ($homeTeam->id === $awayTeam->id || in_array($awayTeam->id, $usedTeamIds)) {
                        continue;
                    }
                    
                    $newMatchups[] = [
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id
                    ];
                    
                    $usedTeamIds[] = $homeTeam->id;
                    $usedTeamIds[] = $awayTeam->id;
                    
                    break;
                }
                
                if (count($newMatchups) >= $gamesPerWeek) {
                    break;
                }
            }
        }
        
        return $newMatchups;
    }
} 