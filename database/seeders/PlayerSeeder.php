<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Database\Seeder;

class PlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {   
        $teams = Team::all();

        foreach ($teams as $team) {
            $this->createGenericPlayers($team->id, $team->name);
        }
    }
    
    /**
     * Create players for a team
     */
    private function createGenericPlayers($teamId, $teamName)
    {
        $positions = [
            'PG', 'SG', 'SF', 'PF', 'C',
            'PG', 'SG', 'SF', 'PF', 'C',
            'SG', 'SF'
        ];
        
        $players = [];
        
        for ($i = 0; $i < count($positions); $i++) {
            $position = $positions[$i];
            $jerseyNumber = $this->getUniqueJerseyNumber($players);
            
            $firstName = $this->getRandomFirstName();
            $lastName = $this->getRandomLastName();
            
            $players[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position' => $position,
                'jersey_number' => $jerseyNumber,
            ];
        }
        
        $this->createPlayersFromArray($players, $teamId);
    }
    
    /**
     * Generate a unique jersey number that hasn't been used yet
     */
    private function getUniqueJerseyNumber($existingPlayers)
    {
        $existingNumbers = array_column($existingPlayers, 'jersey_number');
        
        do {
            $number = rand(0, 99);
        } while (in_array($number, $existingNumbers));
        
        return $number;
    }
    
    /**
     * Helper method to create players from an array
     */
    private function createPlayersFromArray($players, $teamId)
    {
        foreach ($players as $player) {
            Player::create([
                'team_id' => $teamId,
                'first_name' => $player['first_name'],
                'last_name' => $player['last_name'],
                'position' => $player['position'],
                'jersey_number' => $player['jersey_number'],
                'is_active' => true,
            ]);
        }
    }
    
    /**
     * Helper method to get a random first name
     */
    private function getRandomFirstName()
    {
        $firstNames = [
            'James', 'Michael', 'John', 'Robert', 'David', 'William', 'Joseph', 'Thomas', 'Charles', 'Daniel',
            'Anthony', 'Kevin', 'Jason', 'Matthew', 'Brian', 'Mark', 'Justin', 'Joshua', 'Devin', 'Aaron',
            'Brandon', 'Kyle', 'Tyler', 'Jalen', 'Marcus', 'Darius', 'Isaiah', 'Trey', 'Malik', 'Donovan',
            'Jayden', 'Eric', 'Terrence', 'Cameron', 'Andre', 'Jordan', 'Zach', 'Corey', 'Xavier', 'Derrick',
            'LeBron', 'Stephen', 'Giannis', 'Nikola', 'Luka', 'Joel', 'Jayson', 'Jimmy', 'Damian', 'Trae',
            'Ja', 'Paul', 'Bam', 'Kawhi', 'DeMar', 'Klay', 'Zion', 'Bradley', 'Karl', 'Chris'
        ];
        
        return $firstNames[array_rand($firstNames)];
    }
    
    /**
     * Helper method to get a random last name
     */
    private function getRandomLastName()
    {
        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Wilson', 'Anderson', 'Taylor',
            'Thomas', 'Jackson', 'White', 'Harris', 'Martin', 'Thompson', 'Robinson', 'Clark', 'Rodriguez', 'Lewis',
            'Walker', 'Hall', 'Young', 'Allen', 'Wright', 'Scott', 'Green', 'Adams', 'Baker', 'Carter',
            'Mitchell', 'Turner', 'Parker', 'Collins', 'Stewart', 'Morris', 'Murphy', 'Cook', 'Morgan', 'Peterson',
            'James', 'Curry', 'Antetokounmpo', 'Jokic', 'Doncic', 'Embiid', 'Tatum', 'Butler', 'Lillard', 'Young',
            'Morant', 'George', 'Adebayo', 'Leonard', 'DeRozan', 'Thompson', 'Williamson', 'Beal', 'Towns', 'Paul'
        ];
        
        return $lastNames[array_rand($lastNames)];
    }
}
