<template>
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-6">NBA Live Game Simulation</h1>

        <!-- Control Panel -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <h2 class="text-xl font-semibold mb-4">Control Panel</h2>
            <div class="flex space-x-4 mb-4">
                <button 
                    @click="startSimulation" 
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:bg-gray-400"
                    :disabled="isSimulating"
                >
                    Start Simulation
                </button>
                <button 
                    @click="stopSimulation" 
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:bg-gray-400"
                    :disabled="!isSimulating"
                >
                    Stop Simulation
                </button>
                <button 
                    @click="manualUpdate" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-gray-400"
                    :disabled="!isSimulating || isUpdating"
                >
                    Manual Update
                </button>
            </div>

            <div v-if="isSimulating" class="flex items-center">
                <div class="animate-pulse mr-2 h-3 w-3 bg-green-500 rounded-full"></div>
                <span>Simulation in progress: Minute {{ currentMinute }}/48</span>
            </div>
        </div>

        <!-- Top Performers Panel (visible when games are running or completed) -->
        <div v-if="(liveGames.length > 0 || completedGames.length > 0) && getGlobalTopPerformers().allPlayers.length > 0" 
             class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-md p-4 mb-6 text-white">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                League Leaders
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Top Scorers -->
                <div>
                    <h3 class="text-lg font-medium mb-2 border-b border-white/20 pb-1">Top Scorers</h3>
                    <div class="space-y-2">
                        <div v-for="(player, index) in getGlobalTopPerformers().topScorers" :key="`scorer-${index}`"
                             class="flex justify-between items-center p-2 rounded"
                             :class="{'bg-white/20': index === 0}">
                            <div class="flex items-center">
                                <span class="w-5 text-center mr-2">{{ index + 1 }}</span>
                                <div>
                                    <div class="font-medium">{{ player.name }}</div>
                                    <div class="text-xs opacity-80">{{ player.team }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold">{{ player.points }} PTS</div>
                                <div class="text-xs opacity-80">{{ player.fg }}% FG</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Assists -->
                <div>
                    <h3 class="text-lg font-medium mb-2 border-b border-white/20 pb-1">Top Assists</h3>
                    <div class="space-y-2">
                        <div v-for="(player, index) in getGlobalTopPerformers().topAssists" :key="`assist-${index}`"
                             class="flex justify-between items-center p-2 rounded"
                             :class="{'bg-white/20': index === 0}">
                            <div class="flex items-center">
                                <span class="w-5 text-center mr-2">{{ index + 1 }}</span>
                                <div>
                                    <div class="font-medium">{{ player.name }}</div>
                                    <div class="text-xs opacity-80">{{ player.team }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold">{{ player.assists }} AST</div>
                                <div class="text-xs opacity-80">{{ player.points }} PTS</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Selection -->
        <div v-if="!isSimulating && completedGames.length === 0" class="bg-white rounded-lg shadow-md p-4 mb-6">
            <h2 class="text-xl font-semibold mb-4">Select Games to Simulate</h2>
            <div v-if="scheduledGames.length === 0" class="text-gray-500">
                No scheduled games available
            </div>
            <div v-else class="space-y-2">
                <div class="flex items-center mb-4">
                    <input 
                        type="checkbox" 
                        id="select-all" 
                        v-model="selectAll" 
                        @change="toggleSelectAll"
                        class="mr-2"
                    >
                    <label for="select-all" class="font-semibold">Select All Games</label>
                </div>
                <div 
                    v-for="game in scheduledGames" 
                    :key="game.id" 
                    class="flex items-center p-2 hover:bg-gray-100 rounded"
                >
                    <input 
                        type="checkbox" 
                        :id="`game-${game.id}`" 
                        v-model="selectedGames" 
                        :value="game.id"
                        class="mr-2"
                    >
                    <label :for="`game-${game.id}`">
                        {{ game.homeTeam.name }} vs {{ game.awayTeam.name }} 
                        <span class="text-gray-500 text-sm">({{ formatDate(game.scheduled_at) }})</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Live Games -->
        <div v-if="liveGames.length > 0 && isSimulating" class="bg-white rounded-lg shadow-md p-4 mb-6">
            <h2 class="text-lg font-semibold mb-4">Live Games</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="game in liveGames" :key="game.id" class="border rounded-lg overflow-hidden">
                    <!-- Game Header with Teams -->
                    <div class="bg-gray-100 p-3 flex justify-between items-center">
                        <div class="flex items-center">
                            <span class="rounded-full bg-red-100 text-red-800 py-1 px-2 text-xs font-medium">
                                Q{{ game.quarter || 1 }}
                            </span>
                            <span class="ml-2 font-medium">
                                {{ formatGameTime(game.quarterTime || 720) }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-500">
                            Last updated: {{ minuteText(game) }}
                        </span>
                    </div>

                    <!-- Score Board -->
                    <div class="p-4 flex justify-between items-center">
                        <div class="flex-1 text-left">
                            <div class="font-semibold">{{ game.homeTeam?.name }}</div>
                            <div class="text-3xl font-bold text-indigo-700">{{ game.homeScore }}</div>
                        </div>
                        <div class="mx-4 text-gray-400 font-semibold">VS</div>
                        <div class="flex-1 text-right">
                            <div class="font-semibold">{{ game.awayTeam?.name }}</div>
                            <div class="text-3xl font-bold text-indigo-700">{{ game.awayScore }}</div>
                        </div>
                    </div>

                    <!-- Game Statistics -->
                    <div class="px-4 pb-3 border-t border-gray-200">
                        <h3 class="text-sm font-semibold py-2 text-gray-700">Game Statistics</h3>
                        
                        <!-- Attacks Bar Chart -->
                        <div class="mb-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span>{{ game.homeStats?.attacks || 0 }}</span>
                                <span class="font-medium">Attacks</span>
                                <span>{{ game.awayStats?.attacks || 0 }}</span>
                            </div>
                            <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-blue-500" 
                                    :style="`width: ${calculateComparisonPercentage(game.homeStats?.attacks || 0, game.awayStats?.attacks || 0)}%`"
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Shooting Percentages -->
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <!-- 2PT % -->
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span>{{ calculateShotPercentage(game, 'home', 2) }}%</span>
                                    <span class="font-medium">2PT%</span>
                                    <span>{{ calculateShotPercentage(game, 'away', 2) }}%</span>
                                </div>
                                <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-green-500" 
                                        :style="`width: ${calculateComparisonPercentage(calculateShotPercentage(game, 'home', 2), calculateShotPercentage(game, 'away', 2))}%`"
                                    ></div>
                                </div>
                            </div>
                            
                            <!-- 3PT % -->
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span>{{ calculateShotPercentage(game, 'home', 3) }}%</span>
                                    <span class="font-medium">3PT%</span>
                                    <span>{{ calculateShotPercentage(game, 'away', 3) }}%</span>
                                </div>
                                <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-purple-500" 
                                        :style="`width: ${calculateComparisonPercentage(calculateShotPercentage(game, 'home', 3), calculateShotPercentage(game, 'away', 3))}%`"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assists -->
                        <div class="mb-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span>{{ game.homeStats?.assists || 0 }}</span>
                                <span class="font-medium">Assists</span>
                                <span>{{ game.awayStats?.assists || 0 }}</span>
                            </div>
                            <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-yellow-500" 
                                    :style="`width: ${calculateComparisonPercentage(game.homeStats?.assists || 0, game.awayStats?.assists || 0)}%`"
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Top Performers -->
                        <div v-if="Object.keys(game.playerStats || {}).length > 0" class="mt-4">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-sm font-semibold text-gray-700">Top Performers</h3>
                                <button 
                                    @click="togglePlayerStats(game.id)" 
                                    class="text-xs text-blue-600 hover:text-blue-800"
                                >
                                    {{ expandedPlayerStats[game.id] ? 'Hide Details' : 'Show All Players' }}
                                </button>
                            </div>
                            
                            <!-- Hot Player Alert - Shows when a player has an exceptional performance -->
                            <div v-if="getHotPlayer(game)" class="mb-3 bg-amber-100 border-l-4 border-amber-500 p-2 rounded">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-xs font-bold text-amber-800">Hot Player: </span>
                                    <span class="text-xs ml-1">{{ getHotPlayer(game).name }} is on fire! {{ getHotPlayerStat(getHotPlayer(game)) }}</span>
                                </div>
                            </div>
                            
                            <!-- Summary view when not expanded -->
                            <div v-if="!expandedPlayerStats[game.id]" class="grid grid-cols-2 gap-3">
                                <!-- Home Team Top Players -->
                                <div class="bg-blue-50 p-2 rounded">
                                    <h4 class="text-xs font-semibold text-blue-800 mb-1">{{ game.homeTeam?.name }}</h4>
                                    <div v-for="player in getTopPlayers(Object.fromEntries(
                                        Object.entries(game.playerStats || {}).filter(([_, stats]) => stats.team === game.homeTeam?.name)
                                    ), 2)" :key="player.id" class="text-xs mb-1">
                                        <div class="flex justify-between">
                                            <span class="font-medium">{{ player.name }}</span>
                                            <span>{{ player.points }} pts</span>
                                        </div>
                                        <div class="text-gray-500 text-xs">
                                            {{ player.assists }} ast, {{ player.fg || 0 }}% FG
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Away Team Top Players -->
                                <div class="bg-red-50 p-2 rounded">
                                    <h4 class="text-xs font-semibold text-red-800 mb-1">{{ game.awayTeam?.name }}</h4>
                                    <div v-for="player in getTopPlayers(Object.fromEntries(
                                        Object.entries(game.playerStats || {}).filter(([_, stats]) => stats.team === game.awayTeam?.name)
                                    ), 2)" :key="player.id" class="text-xs mb-1">
                                        <div class="flex justify-between">
                                            <span class="font-medium">{{ player.name }}</span>
                                            <span>{{ player.points }} pts</span>
                                        </div>
                                        <div class="text-gray-500 text-xs">
                                            {{ player.assists }} ast, {{ player.fg || 0 }}% FG
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Expanded detailed player stats -->
                            <div v-else class="mt-2">
                                <div class="grid grid-cols-1 gap-3">
                                    <!-- Home Team All Players -->
                                    <div class="bg-blue-50 p-2 rounded">
                                        <h4 class="text-xs font-semibold text-blue-800 mb-2">{{ game.homeTeam?.name }} Players</h4>
                                        
                                        <!-- Header -->
                                        <div class="grid grid-cols-5 text-xs font-medium text-gray-600 mb-1 border-b pb-1">
                                            <div>Player</div>
                                            <div class="text-center">PTS</div>
                                            <div class="text-center">AST</div>
                                            <div class="text-center">FG</div>
                                            <div class="text-center">FG%</div>
                                        </div>
                                        
                                        <!-- Player rows -->
                                        <div 
                                            v-for="player in Object.entries(game.playerStats || {})
                                                .filter(([_, stats]) => stats.team === game.homeTeam?.name)
                                                .map(([id, stats]) => ({ id, ...stats }))
                                                .sort((a, b) => b.points - a.points)"
                                            :key="player.id"
                                            class="grid grid-cols-5 text-xs py-1 border-b border-blue-100"
                                        >
                                            <div class="font-medium">{{ player.name }}</div>
                                            <div class="text-center">{{ player.points }}</div>
                                            <div class="text-center">{{ player.assists }}</div>
                                            <div class="text-center">{{ player.fgMade || 0 }}/{{ player.fgAttempted || 0 }}</div>
                                            <div class="text-center">{{ player.fg || 0 }}%</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Away Team All Players -->
                                    <div class="bg-red-50 p-2 rounded mt-2">
                                        <h4 class="text-xs font-semibold text-red-800 mb-2">{{ game.awayTeam?.name }} Players</h4>
                                        
                                        <!-- Header -->
                                        <div class="grid grid-cols-5 text-xs font-medium text-gray-600 mb-1 border-b pb-1">
                                            <div>Player</div>
                                            <div class="text-center">PTS</div>
                                            <div class="text-center">AST</div>
                                            <div class="text-center">FG</div>
                                            <div class="text-center">FG%</div>
                                        </div>
                                        
                                        <!-- Player rows -->
                                        <div 
                                            v-for="player in Object.entries(game.playerStats || {})
                                                .filter(([_, stats]) => stats.team === game.awayTeam?.name)
                                                .map(([id, stats]) => ({ id, ...stats }))
                                                .sort((a, b) => b.points - a.points)"
                                            :key="player.id"
                                            class="grid grid-cols-5 text-xs py-1 border-b border-red-100"
                                        >
                                            <div class="font-medium">{{ player.name }}</div>
                                            <div class="text-center">{{ player.points }}</div>
                                            <div class="text-center">{{ player.assists }}</div>
                                            <div class="text-center">{{ player.fgMade || 0 }}/{{ player.fgAttempted || 0 }}</div>
                                            <div class="text-center">{{ player.fg || 0 }}%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Game Events -->
                    <div v-if="game.events && game.events.length > 0" class="bg-gray-50 p-3 border-t border-gray-200">
                        <h3 class="text-sm font-semibold mb-2">Recent Events</h3>
                        <div 
                            v-for="event in getRecentEvents(game.events)" 
                            :key="event.id" 
                            class="text-sm mb-1 py-1 px-2 rounded"
                            :class="{ 'bg-blue-100': event.isNew }"
                        >
                            <div class="flex items-center">
                                <span class="text-xs font-medium text-gray-500 mr-2">
                                    Q{{ event.quarter }} {{ formatGameTime(event.time) }}
                                </span>
                                <span>{{ event.description }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Simulation Complete Message -->
        <div v-if="completedGames.length > 0 && !isSimulating" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
            <div class="flex items-center">
                <svg class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <p class="font-medium">Simulation Complete</p>
            </div>
            <p class="mt-1">All games have finished. Final scores are displayed below.</p>
            <div class="mt-3">
                <button 
                    @click="resetSimulation" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Generate Next Week Matches
                </button>
            </div>
        </div>

        <!-- Completed games -->
        <div v-if="completedGames.length > 0" class="bg-white rounded-lg shadow-md p-4 mb-6">
            <h2 class="text-lg font-semibold mb-4">{{ isSimulating ? 'Completed Games' : 'Final Scores' }}</h2>
            
            <!-- Game summary cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="game in completedGames" :key="game.id" class="border rounded-lg overflow-hidden">
                    <!-- Game Header with Final score tag -->
                    <div class="bg-gray-100 p-3 flex justify-between items-center">
                        <span class="rounded-full bg-green-100 text-green-800 py-1 px-2 text-xs font-medium">
                            Final
                        </span>
                        <span class="text-xs text-gray-500">
                            Game Complete
                        </span>
                    </div>
                    
                    <!-- Score Board -->
                    <div class="p-4 flex justify-between items-center">
                        <div class="flex-1 text-left">
                            <div class="font-semibold">{{ game.homeTeam?.name }}</div>
                            <div class="text-3xl font-bold" :class="getWinnerClass(game, 'home')">{{ game.homeScore }}</div>
                        </div>
                        <div class="mx-4 text-gray-400 font-semibold">VS</div>
                        <div class="flex-1 text-right">
                            <div class="font-semibold">{{ game.awayTeam?.name }}</div>
                            <div class="text-3xl font-bold" :class="getWinnerClass(game, 'away')">{{ game.awayScore }}</div>
                        </div>
                    </div>
                    
                    <!-- Game Statistics (if available) -->
                    <div v-if="game.homeStats || game.awayStats" class="px-4 pb-3 border-t border-gray-200">
                        <h3 class="text-sm font-semibold py-2 text-gray-700">Game Statistics</h3>
                        
                        <!-- Shooting Summary -->
                        <div class="grid grid-cols-2 gap-4 mb-2">
                            <!-- Home Team Stats -->
                            <div class="bg-blue-50 p-2 rounded">
                                <h4 class="text-xs font-semibold text-blue-800 mb-1">{{ game.homeTeam?.name }}</h4>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <div class="font-medium">Attacks</div>
                                        <div>{{ game.homeStats?.attacks || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="font-medium">Assists</div>
                                        <div>{{ game.homeStats?.assists || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="font-medium">2PT%</div>
                                        <div>{{ calculateShotPercentage(game, 'home', 2) }}%</div>
                                    </div>
                                    <div>
                                        <div class="font-medium">3PT%</div>
                                        <div>{{ calculateShotPercentage(game, 'home', 3) }}%</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Away Team Stats -->
                            <div class="bg-red-50 p-2 rounded">
                                <h4 class="text-xs font-semibold text-red-800 mb-1">{{ game.awayTeam?.name }}</h4>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <div class="font-medium">Attacks</div>
                                        <div>{{ game.awayStats?.attacks || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="font-medium">Assists</div>
                                        <div>{{ game.awayStats?.assists || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="font-medium">2PT%</div>
                                        <div>{{ calculateShotPercentage(game, 'away', 2) }}%</div>
                                    </div>
                                    <div>
                                        <div class="font-medium">3PT%</div>
                                        <div>{{ calculateShotPercentage(game, 'away', 3) }}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Performers -->
                        <div v-if="Object.keys(game.playerStats || {}).length > 0">
                            <h4 class="text-xs font-semibold text-gray-700 mb-1">Top Scorers</h4>
                            <div class="space-y-1">
                                <div 
                                    v-for="player in getTopPlayers(game.playerStats, 3)" 
                                    :key="player.id"
                                    class="flex justify-between text-xs border-b border-gray-100 pb-1"
                                >
                                    <span>{{ player.name }}</span>
                                    <span class="font-medium">{{ player.points }} pts, {{ player.assists }} ast</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Games Message -->
        <div v-if="!isSimulating && completedGames.length === 0 && scheduledGames.length === 0" class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded mb-6">
            <p class="font-medium">No games available</p>
            <p class="mt-1">There are no scheduled or completed games to display.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, defineComponent } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';

// GuestLayout component for non-authenticated users
const GuestLayout = defineComponent({
    template: `
        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <h2 class="text-xl font-bold">NBA Simulator</h2>
                        </div>
                    </div>
                </div>
            </nav>
            <main>
                <slot></slot>
            </main>
        </div>
    `
});

// Use the Guest Layout wrapper around the entire template
defineComponent({
    layout: GuestLayout
});

// State
const scheduledGames = ref([]);
const liveGames = ref([]);
const completedGames = ref([]);
const selectedGames = ref([]);
const selectAll = ref(false);
const isSimulating = ref(false);
const isUpdating = ref(false);
const updateInterval = ref(null);
const currentMinute = ref(0);
const simulationWorker = ref(null);

// For convenience and reactive state
const expandedPlayerStats = ref({});

// Toggle player stats expansion for a game
const togglePlayerStats = (gameId) => {
    expandedPlayerStats.value[gameId] = !expandedPlayerStats.value[gameId];
};

// Fetch scheduled games on mount
onMounted(async () => {
    await fetchScheduledGames();
    await checkSimulationStatus();
    
    // Set up simulation worker
    setupSimulationWorker();
});

// Clean up on unmount
onBeforeUnmount(() => {
    stopSimulationWorker();
});

// Fetch all scheduled games
const fetchScheduledGames = async () => {
    try {
        console.log('Fetching scheduled games...');
        const response = await axios.get('/api/games', {
            params: { status: 'scheduled' }
        });
        console.log('API response:', response);
        
        // Check different possible response formats
        let gamesData;
        if (Array.isArray(response.data)) {
            // Direct array response
            gamesData = response.data;
        } else if (response.data && Array.isArray(response.data.data)) {
            // Nested data property containing the array
            gamesData = response.data.data;
        } else {
            console.error('Unexpected API response format:', response.data);
            return;
        }
        
        console.log(`Found ${gamesData.length} scheduled games`);
        // Transform the data to match the expected format
        scheduledGames.value = gamesData.map(game => ({
            id: game.id,
            scheduled_at: game.scheduled_at,
            // Transform snake_case to camelCase for team properties
            homeTeam: game.home_team,
            awayTeam: game.away_team
        }));
    } catch (error) {
        console.error('Error fetching scheduled games:', error);
        if (error.response) {
            console.error('Error response:', error.response.status, error.response.data);
        }
    }
};

// Check if simulation is already in progress
const checkSimulationStatus = async () => {
    try {
        const response = await axios.get('/api/simulation/state');
        console.log('Simulation state response:', response.data);
        
        // Check if simulation is active based on the correct response structure
        if (response.data.success && response.data.data && response.data.data.active) {
            isSimulating.value = true;
            
            // Fetch details for active and completed games
            const activeGameIds = response.data.data.active_games || [];
            const completedGameIds = response.data.data.completed_games || [];
            
            if (activeGameIds.length > 0) {
                await fetchGamesDetails([...activeGameIds, ...completedGameIds]);
            }
            
            // Set current minute based on game progress data
            const gameProgress = response.data.data.game_progress || {};
            if (Object.keys(gameProgress).length > 0) {
                const firstGameId = Object.keys(gameProgress)[0];
                currentMinute.value = gameProgress[firstGameId].current_minute;
            }
        }
    } catch (error) {
        console.error('Error checking simulation status:', error);
    }
};

// Fetch details for specific games
const fetchGamesDetails = async (gameIds) => {
    if (!gameIds || !gameIds.length) {
        console.log('No game IDs provided to fetchGamesDetails');
        return;
    }
    
    try {
        console.log('Fetching details for games:', gameIds);
        const response = await axios.get('/api/games', {
            params: { ids: gameIds.join(',') }
        });
        console.log('Game details response:', response.data);
        
        // Check different possible response formats
        let gamesData;
        if (Array.isArray(response.data)) {
            // Direct array response
            gamesData = response.data;
        } else if (response.data && Array.isArray(response.data.data)) {
            // Nested data property containing the array
            gamesData = response.data.data;
        } else {
            console.error('Unexpected API response format in fetchGamesDetails:', response.data);
            return;
        }
        
        console.log(`Found ${gamesData.length} games in response`);
        
        // If we didn't get all requested games, log which ones are missing
        if (gamesData.length < gameIds.length) {
            const returnedIds = gamesData.map(g => g.id);
            const missingIds = gameIds.filter(id => !returnedIds.includes(parseInt(id)) && !returnedIds.includes(id.toString()));
            console.warn(`Missing ${missingIds.length} games in API response:`, missingIds);
        }
        
        // Keep track of processed games for debugging
        const processedLiveGames = [];
        const processedCompletedGames = [];
        
        // Separate into live and completed games
        gamesData.forEach(game => {
            // Transform the data to match expected format
            const transformedGame = {
                id: game.id,
                homeTeam: game.home_team,
                awayTeam: game.away_team,
                homeScore: game.home_team_score || 0,
                awayScore: game.away_team_score || 0,
                quarter: game.current_quarter || 1,
                quarterTime: game.quarter_time_seconds || 720, // Default to 12 minutes
                events: game.events || [],
                status: game.status
            };
            
            console.log(`Processing game ${game.id} with status: ${game.status}`);
            
            if (game.status === 'completed') {
                // Check if already in the completed list
                if (!completedGames.value.some(g => g.id === game.id)) {
                    const completedGame = {
                        id: transformedGame.id,
                        homeTeam: transformedGame.homeTeam,
                        awayTeam: transformedGame.awayTeam,
                        homeScore: transformedGame.homeScore,
                        awayScore: transformedGame.awayScore
                    };
                    completedGames.value.push(completedGame);
                    processedCompletedGames.push(completedGame);
                }
            } else {
                // Update or add to live games
                const existingIndex = liveGames.value.findIndex(g => g.id === game.id);
                
                if (existingIndex >= 0) {
                    liveGames.value[existingIndex] = transformedGame;
                } else {
                    liveGames.value.push(transformedGame);
                }
                processedLiveGames.push(transformedGame);
            }
            
            // Check for events in the API response
            if (game.events && game.events.length) {
                console.log(`API returned ${game.events.length} events for game ${game.id}`);
                
                // Mark these events specifically to track their source
                transformedGame.events = game.events.map(event => ({
                    ...event,
                    source: 'initial_fetch'
                }));
            }
        });
        
        // Force reactivity
        liveGames.value = [...liveGames.value];
        completedGames.value = [...completedGames.value];
        
        console.log('Processed live games:', processedLiveGames);
        console.log('Processed completed games:', processedCompletedGames);
        console.log('Current live games state:', liveGames.value);
        console.log('Current completed games state:', completedGames.value);
    } catch (error) {
        console.error('Error fetching game details:', error);
        if (error.response) {
            console.error('Error response:', error.response.status, error.response.data);
        }
    }
};

// Toggle select all games
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedGames.value = scheduledGames.value.map(game => game.id);
    } else {
        selectedGames.value = [];
    }
};

// Start the simulation
const startSimulation = async () => {
    if (selectedGames.value.length === 0) {
        alert('Please select at least one game to simulate');
        return;
    }
    
    try {
        console.log('Starting simulation with game IDs:', selectedGames.value);
        const response = await axios.post('/api/simulation/start', {
            game_ids: selectedGames.value
        });
        console.log('Start simulation response:', response.data);
        
        if (response.data.success) {
            // Clear state before starting new simulation
            liveGames.value = [];
            completedGames.value = [];
            currentMinute.value = 0;
            isSimulating.value = true;
            
            console.log('Fetching initial game details...');
            // Fetch initial game details before starting the worker
            await fetchGamesDetails(selectedGames.value);
            console.log('Initial live games:', liveGames.value);
            
            // Force an immediate update to get initial events
            try {
                console.log('Getting initial simulation state...');
                const stateResponse = await axios.get('/api/simulation/state');
                
                if (stateResponse.data.success && stateResponse.data.data) {
                    const gameProgress = stateResponse.data.data.game_progress || {};
                    
                    // Check for any events in the state
                    for (const gameId in gameProgress) {
                        const gameData = gameProgress[gameId];
                        if (gameData.events && gameData.events.length) {
                            console.log(`Found initial events in state for game ${gameId}:`, gameData.events);
                            
                            // Add these events to the appropriate game
                            const gameIndex = liveGames.value.findIndex(g => g.id == gameId);
                            if (gameIndex >= 0) {
                                processGameEvents(liveGames.value[gameIndex], gameData.events);
                            }
                        }
                    }
                }
            } catch (stateError) {
                console.error('Error fetching initial state:', stateError);
            }
            
            // Start simulation worker only after we have the initial game data
            startSimulationWorker();
            
            // Immediately request an update to make sure we have the latest data
            await processUpdate();
        } else {
            alert(response.data.message || 'Failed to start simulation');
        }
    } catch (error) {
        console.error('Error starting simulation:', error);
        alert('Error starting simulation: ' + (error.response?.data?.message || error.message));
    }
};

// Stop the simulation
const stopSimulation = async () => {
    try {
        console.log('Stopping simulation...');
        
        // First get the current state to ensure we have all active games
        try {
            const stateResponse = await axios.get('/api/simulation/state');
            console.log('Current simulation state before stopping:', stateResponse.data);
            
            if (stateResponse.data.success && stateResponse.data.data) {
                const activeGameIds = stateResponse.data.data.active_games || [];
                const completedGameIds = stateResponse.data.data.completed_games || [];
                
                // Fetch any missing games before stopping
                const allGameIds = [...activeGameIds, ...completedGameIds];
                const missingGameIds = allGameIds.filter(
                    id => !liveGames.value.some(game => game.id == id) && 
                         !completedGames.value.some(game => game.id == id)
                );
                
                if (missingGameIds.length > 0) {
                    console.log('Fetching missing games before stopping:', missingGameIds);
                    await fetchGamesDetails(missingGameIds);
                }
            }
        } catch (stateError) {
            console.error('Error fetching simulation state before stopping:', stateError);
            // Continue with stop even if state fetch fails
        }
        
        // Now stop the simulation
        const response = await axios.post('/api/simulation/stop');
        console.log('Stop simulation response:', response.data);
        
        if (response.data.success) {
            isSimulating.value = false;
            stopSimulationWorker();
            
            // Move all live games to completed
            liveGames.value.forEach(game => {
                // Avoid duplicates in completed games
                if (!completedGames.value.some(cg => cg.id === game.id)) {
                    completedGames.value.push({
                        id: game.id,
                        homeTeam: game.homeTeam,
                        awayTeam: game.awayTeam,
                        homeScore: game.homeScore,
                        awayScore: game.awayScore
                    });
                }
            });
            
            liveGames.value = [];
            
            // Refresh scheduled games
            await fetchScheduledGames();
        } else {
            alert(response.data.message || 'Failed to stop simulation');
        }
    } catch (error) {
        console.error('Error stopping simulation:', error);
        
        // Even if the API call fails, stop the simulation on the frontend side
        isSimulating.value = false;
        stopSimulationWorker();
        liveGames.value = [];
        
        alert('Error stopping simulation: ' + (error.response?.data?.message || error.message));
        
        // Refresh scheduled games anyway
        try {
            await fetchScheduledGames();
        } catch (fetchError) {
            console.error('Error refreshing scheduled games after stop error:', fetchError);
        }
    }
};

// Update the simulation manually
const manualUpdate = async () => {
    if (isUpdating.value) return;
    
    isUpdating.value = true;
    try {
        await processUpdate();
    } finally {
        isUpdating.value = false;
    }
};

// Start periodic updates every 5 seconds
const startPeriodicUpdates = () => {
    stopPeriodicUpdates(); // Clear any existing interval
    updateInterval.value = setInterval(async () => {
        if (!isUpdating.value) {
            isUpdating.value = true;
            try {
                await processUpdate();
            } finally {
                isUpdating.value = false;
            }
        }
    }, 5000); // 5 seconds
};

// Stop periodic updates
const stopPeriodicUpdates = () => {
    if (updateInterval.value) {
        clearInterval(updateInterval.value);
        updateInterval.value = null;
    }
};

// Process an update
const processUpdate = async () => {
    if (isUpdating.value) return;
    
    isUpdating.value = true;
    try {
        console.log('Sending update request...');
        const response = await axios.post('/api/simulation/update');
        console.log('Update response:', response.data);
        
        if (response.data.success) {
            // Update current minute
            currentMinute.value++;
            
            // Get data from the proper location in the response
            const responseData = response.data.data || {};
            console.log('Response data extracted:', responseData);
            
            // Process updates for each game
            const updates = responseData.updates || {};
            console.log('Game updates:', updates);
            
            // Check if we have any updates to process
            if (Object.keys(updates).length === 0) {
                console.log('No game updates received in this cycle');
                return;
            }
            
            // Debug the update structure deeply
            for (const gameId in updates) {
                const gameUpdate = updates[gameId];
                console.log(`Deep inspection of update for game ${gameId}:`, JSON.stringify(gameUpdate, null, 2));
                
                // Specifically log any events to ensure they're coming through
                if (gameUpdate.events && gameUpdate.events.length) {
                    console.log(`Game ${gameId} events:`, gameUpdate.events);
                }
            }
            
            // First, get the state to find active game IDs
            try {
                const stateResponse = await axios.get('/api/simulation/state');
                console.log('Current simulation state:', stateResponse.data);
                
                if (stateResponse.data.success && stateResponse.data.data) {
                    const activeGameIds = stateResponse.data.data.active_games || [];
                    
                    // If we have active games but they're not in our liveGames array, fetch them first
                    const missingGameIds = activeGameIds.filter(
                        id => !liveGames.value.some(game => game.id == id)
                    );
                    
                    if (missingGameIds.length > 0) {
                        console.log('Found missing games that need to be fetched:', missingGameIds);
                        await fetchGamesDetails(missingGameIds);
                    }
                    
                    // If the state includes game events, process them too
                    const gameProgress = stateResponse.data.data.game_progress || {};
                    for (const gameId in gameProgress) {
                        const gameData = gameProgress[gameId];
                        if (gameData.events && gameData.events.length) {
                            console.log(`Found events in state data for game ${gameId}:`, gameData.events);
                            
                            // Find the game in live games
                            const gameIndex = liveGames.value.findIndex(g => g.id == gameId);
                            if (gameIndex >= 0) {
                                console.log(`Adding state events to game ${gameId}`);
                                processGameEvents(liveGames.value[gameIndex], gameData.events);
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Error fetching simulation state:', error);
            }
            
            // Process each game update
            for (const gameId in updates) {
                const gameUpdate = updates[gameId];
                console.log(`Processing update for game ${gameId}:`, gameUpdate);
                
                // Get the numeric game ID from the active_games array if possible
                let numericGameId = parseInt(gameId);
                if (isNaN(numericGameId)) {
                    console.warn(`Non-numeric game ID: ${gameId}, using as is`);
                    numericGameId = gameId;
                }
                
                // Find the game in live games
                let gameIndex = liveGames.value.findIndex(g => g.id == numericGameId);
                
                // If game not found by ID, try to find by index if gameId looks like a numeric index
                if (gameIndex < 0 && !isNaN(parseInt(gameId))) {
                    const activeGames = liveGames.value.filter(g => g.status !== 'completed');
                    const index = parseInt(gameId);
                    if (index >= 0 && index < activeGames.length) {
                        console.log(`Game ID ${gameId} looks like an index, trying to match with active game index ${index}`);
                        gameIndex = liveGames.value.indexOf(activeGames[index]);
                    }
                }
                
                if (gameIndex >= 0) {
                    console.log(`Found game at index ${gameIndex}:`, liveGames.value[gameIndex]);
                    
                    // Create a new game object with updated properties
                    const updatedGame = { ...liveGames.value[gameIndex] };
                    
                    // Add a minute property to the game object to track updates
                    updatedGame.minute = gameUpdate.minute || currentMinute.value;
                    
                    // Update the quarter time based on the minute (if not provided in events)
                    // A quarter is 12 minutes (720 seconds). Each minute = 60 seconds.
                    // Calculate time remaining in the current quarter
                    const currentQuarter = updatedGame.quarter || 1;
                    const currentMinuteInGame = updatedGame.minute || 1;
                    const minutesPerQuarter = 12;
                    
                    // Calculate which quarter we should be in and the time remaining
                    const totalGameMinutes = currentMinuteInGame - 1; // 0-based for calculation
                    const calculatedQuarter = Math.floor(totalGameMinutes / minutesPerQuarter) + 1;
                    const minutesInCurrentQuarter = totalGameMinutes % minutesPerQuarter;
                    const secondsRemaining = (minutesPerQuarter - minutesInCurrentQuarter - 1) * 60 + (60 - (currentMinuteInGame % 1) * 60);
                    
                    console.log(`Game time calculation: Total minutes=${totalGameMinutes}, Quarter=${calculatedQuarter}, Minutes in quarter=${minutesInCurrentQuarter}, Seconds remaining=${secondsRemaining}`);
                    
                    // Only update the quarter and time if not explicitly set in the update
                    if (!gameUpdate.events || gameUpdate.events.length === 0 || !gameUpdate.events.some(e => e.time)) {
                        // If the calculated quarter is different, update quarter and reset timer
                        if (calculatedQuarter !== updatedGame.quarter) {
                            // Add a quarter change event
                            if (!updatedGame.events) updatedGame.events = [];
                            updatedGame.events.push({
                                id: `quarter-change-${Date.now()}`,
                                time: 720, // Full quarter time
                                quarter: calculatedQuarter,
                                description: `Start of Quarter ${calculatedQuarter}`,
                                type: 'quarter_change',
                                isNew: true
                            });
                            
                            updatedGame.quarter = calculatedQuarter;
                            updatedGame.quarterTime = 720; // Reset to full quarter time
                        } else {
                            // Update the time remaining in the current quarter
                            updatedGame.quarterTime = Math.max(0, Math.round(secondsRemaining));
                        }
                        
                        console.log(`Updated quarter: ${updatedGame.quarter}, time: ${updatedGame.quarterTime}`);
                    }
                    
                    // For development: Generate random scores if server scores are zero
                    const initialHome = gameUpdate.initialScore?.home || 0;
                    const initialAway = gameUpdate.initialScore?.away || 0;
                    const finalHome = gameUpdate.finalScore?.home || 0;
                    const finalAway = gameUpdate.finalScore?.away || 0;
                    
                    // Log the score details
                    console.log(`Game ${gameId} score details:`, { 
                        initial: gameUpdate.initialScore,
                        final: gameUpdate.finalScore,
                        currentInGame: { home: updatedGame.homeScore, away: updatedGame.awayScore }
                    });
                    
                    // If both initial and final scores are 0, the server is not calculating scores correctly
                    const allZeros = initialHome === 0 && initialAway === 0 && finalHome === 0 && finalAway === 0;
                    
                    // Only update the score if there's a real score change or we're generating random scores
                    if (gameUpdate.finalScore) {
                        if (allZeros) {
                            // For testing only: simulate score changes by adding random points
                            // Remove this in production
                            const homeAdd = Math.floor(Math.random() * 3);
                            const awayAdd = Math.floor(Math.random() * 3);
                            updatedGame.homeScore += homeAdd;
                            updatedGame.awayScore += awayAdd;
                            console.log(`DEV MODE: Adding random points ${homeAdd}-${awayAdd}, new score: ${updatedGame.homeScore}-${updatedGame.awayScore}`);
                            
                            // Create synthetic events for the score changes if needed
                            if (homeAdd > 0) {
                                if (!updatedGame.events) updatedGame.events = [];
                                updatedGame.events.push({
                                    id: `synthetic-${Date.now()}-home`,
                                    time: updatedGame.quarterTime || 720,
                                    quarter: updatedGame.quarter || 1,
                                    description: `${updatedGame.homeTeam.name} scores ${homeAdd} points`,
                                    type: 'score',
                                    isNew: true
                                });
                                
                                // Update team/player stats for dev mode
                                updateGameStats(updatedGame, 'home', homeAdd, true);
                            }
                            
                            if (awayAdd > 0) {
                                if (!updatedGame.events) updatedGame.events = [];
                                updatedGame.events.push({
                                    id: `synthetic-${Date.now()}-away`,
                                    time: updatedGame.quarterTime || 720,
                                    quarter: updatedGame.quarter || 1,
                                    description: `${updatedGame.awayTeam.name} scores ${awayAdd} points`,
                                    type: 'score',
                                    isNew: true
                                });
                                
                                // Update team/player stats for dev mode
                                updateGameStats(updatedGame, 'away', awayAdd, true);
                            }
                        } else {
                            // Use the server's score
                            const homeDiff = finalHome - updatedGame.homeScore;
                            const awayDiff = finalAway - updatedGame.awayScore;
                            
                            updatedGame.homeScore = finalHome;
                            updatedGame.awayScore = finalAway;
                            console.log(`Updated score: ${updatedGame.homeScore}-${updatedGame.awayScore}`);
                            
                            // Update the statistics based on score changes
                            if (homeDiff > 0) {
                                updateGameStats(updatedGame, 'home', homeDiff, false);
                            }
                            if (awayDiff > 0) {
                                updateGameStats(updatedGame, 'away', awayDiff, false);
                            }
                        }
                    }
                    
                    // Add new events
                    if (gameUpdate.events && gameUpdate.events.length) {
                        processGameEvents(updatedGame, gameUpdate.events);
                        
                        // Update quarter and time based on latest event
                        const latestEvent = gameUpdate.events[gameUpdate.events.length - 1];
                        if (latestEvent) {
                            // If the event contains a quarter, use it
                            if (latestEvent.quarter) {
                                updatedGame.quarter = latestEvent.quarter;
                            }
                            
                            // If the event contains a time, use it
                            if (latestEvent.time !== undefined) {
                                updatedGame.quarterTime = latestEvent.time;
                            }
                            
                            console.log(`Updated from event - quarter: ${updatedGame.quarter}, time: ${updatedGame.quarterTime}`);
                        }
                    }
                    
                    // Replace the game in the array to trigger reactivity
                    liveGames.value[gameIndex] = updatedGame;
                } else {
                    console.warn(`Game with ID ${gameId} not found in live games. Will try to fetch it.`);
                    // Try to fetch game details directly
                    try {
                        const gameResponse = await axios.get(`/api/games/${numericGameId}`);
                        console.log(`Fetched missing game ${numericGameId}:`, gameResponse.data);
                        
                        if (gameResponse.data) {
                            const game = gameResponse.data;
                            const transformedGame = {
                                id: game.id,
                                homeTeam: game.home_team,
                                awayTeam: game.away_team,
                                homeScore: game.home_team_score || gameUpdate.finalScore?.home || 0,
                                awayScore: game.away_team_score || gameUpdate.finalScore?.away || 0,
                                quarter: game.current_quarter || 1,
                                quarterTime: game.quarter_time_seconds || 720,
                                events: game.events || gameUpdate.events || [],
                                status: game.status,
                                minute: gameUpdate.minute || currentMinute.value,
                                // Add empty stats objects for new games
                                homeStats: { 
                                    attacks: 0,
                                    assists: 0,
                                    twoPointsAttempted: 0,
                                    twoPointsMade: 0,
                                    threePointsAttempted: 0,
                                    threePointsMade: 0
                                },
                                awayStats: {
                                    attacks: 0,
                                    assists: 0,
                                    twoPointsAttempted: 0,
                                    twoPointsMade: 0,
                                    threePointsAttempted: 0,
                                    threePointsMade: 0
                                },
                                playerStats: {}
                            };
                            
                            // Add to live games if it's not completed
                            if (game.status !== 'completed') {
                                liveGames.value.push(transformedGame);
                                console.log(`Added missing game ${numericGameId} to live games`);
                            }
                        }
                    } catch (fetchError) {
                        console.error(`Failed to fetch missing game ${numericGameId}:`, fetchError);
                        
                        // Create a fake game object from the update data if we can't fetch it
                        // This prevents repeated failing requests for the same missing game
                        if (gameUpdate) {
                            console.log(`Creating placeholder game for ID ${numericGameId} from update data`);
                            const placeholderGame = {
                                id: numericGameId,
                                homeTeam: { name: "Home Team", abbreviation: "HOME" },
                                awayTeam: { name: "Away Team", abbreviation: "AWAY" },
                                homeScore: gameUpdate.finalScore?.home || 0,
                                awayScore: gameUpdate.finalScore?.away || 0,
                                quarter: 1,
                                quarterTime: 720,
                                events: gameUpdate.events || [],
                                status: 'in_progress',
                                minute: gameUpdate.minute || currentMinute.value,
                                isPlaceholder: true,  // Mark as placeholder so we can handle it specially if needed
                                // Add empty stats objects for placeholder games
                                homeStats: { 
                                    attacks: 0,
                                    assists: 0,
                                    twoPointsAttempted: 0,
                                    twoPointsMade: 0,
                                    threePointsAttempted: 0,
                                    threePointsMade: 0
                                },
                                awayStats: {
                                    attacks: 0,
                                    assists: 0,
                                    twoPointsAttempted: 0,
                                    twoPointsMade: 0,
                                    threePointsAttempted: 0,
                                    threePointsMade: 0
                                },
                                playerStats: {}
                            };
                            liveGames.value.push(placeholderGame);
                            console.log(`Added placeholder game for ${numericGameId} to live games`);
                        }
                    }
                }
            }
            
            // Force Vue to recognize the array change
            liveGames.value = [...liveGames.value];
            
            // Add a countdown timer update at the end of each update cycle
            liveGames.value.forEach(game => {
                if (game.status !== 'completed') {
                    // Decrease time by 5 seconds each update (adjust as needed based on your game rules)
                    const newTime = Math.max(0, (game.quarterTime || 720) - 5);
                    
                    // If timer reaches zero, move to next quarter
                    if (newTime === 0) {
                        const nextQuarter = (game.quarter || 1) + 1;
                        if (nextQuarter <= 4) {
                            // Add a quarter change event
                            if (!game.events) game.events = [];
                            game.events.push({
                                id: `end-quarter-${Date.now()}`,
                                time: 0,
                                quarter: game.quarter || 1,
                                description: `End of Quarter ${game.quarter || 1}`,
                                type: 'quarter_end',
                                isNew: true
                            });
                            
                            // Start next quarter
                            game.quarter = nextQuarter;
                            game.quarterTime = 720; // Reset to full quarter time
                            
                            // Add start of next quarter event
                            game.events.push({
                                id: `start-quarter-${Date.now()}`,
                                time: 720,
                                quarter: nextQuarter,
                                description: `Start of Quarter ${nextQuarter}`,
                                type: 'quarter_start',
                                isNew: true
                            });
                        }
                    } else {
                        game.quarterTime = newTime;
                    }
                }
            });
            
            // Check if any games completed
            const completedGamesList = responseData.completed_games || [];
            console.log('Completed games:', completedGamesList);
            
            if (completedGamesList.length > 0) {
                const completedGameIds = completedGamesList;
                
                // Move completed games from live to completed
                const remainingGames = [];
                const newlyCompletedGames = [];
                
                liveGames.value.forEach(game => {
                    if (completedGameIds.includes(game.id)) {
                        newlyCompletedGames.push({
                            id: game.id,
                            homeTeam: game.homeTeam,
                            awayTeam: game.awayTeam,
                            homeScore: game.homeScore,
                            awayScore: game.awayScore
                        });
                    } else {
                        remainingGames.push(game);
                    }
                });
                
                // Update liveGames and completedGames to trigger reactivity
                liveGames.value = remainingGames;
                
                if (newlyCompletedGames.length > 0) {
                    completedGames.value = [...completedGames.value, ...newlyCompletedGames];
                    console.log(`Moved ${newlyCompletedGames.length} games to completed`);
                }
                
                // If no more live games, stop simulation
                if (liveGames.value.length === 0) {
                    console.log('No more live games, stopping simulation');
                    isSimulating.value = false;
                    stopSimulationWorker();
                }
            }
        } else {
            console.error('Update failed:', response.data.message);
            if (response.data.message && response.data.message.includes('No active simulation')) {
                isSimulating.value = false;
                stopSimulationWorker();
            }
        }
    } catch (error) {
        console.error('Error processing update:', error);
        if (error.response?.status === 404 || 
            error.response?.data?.message?.includes('No active simulation')) {
            isSimulating.value = false;
            stopSimulationWorker();
        }
    } finally {
        isUpdating.value = false;
    }
};

// New function to process game events
const processGameEvents = (game, events) => {
    if (!events || !events.length) return;
    
    console.log(`Processing ${events.length} events for game ${game.id}`);
    
    // Initialize events array if needed
    if (!game.events) {
        game.events = [];
    }
    
    // Mark all events as new so they stand out in the UI
    const processedEvents = events.map(event => ({
        ...event,
        isNew: true,
        // Ensure required fields are present
        description: event.description || 'Game update',
        time: event.time || 0,
        quarter: event.quarter || 1,
        id: event.id || `event-${Date.now()}-${Math.random()}`
    }));
    
    // Check if we have any score events and log them specifically
    const scoreEvents = processedEvents.filter(e => 
        e.type === 'score' || 
        e.description.toLowerCase().includes('score') || 
        e.description.toLowerCase().includes('point')
    );
    
    if (scoreEvents.length) {
        console.log(`Found ${scoreEvents.length} score events:`, scoreEvents);
    }
    
    // Append new events
    game.events = [...game.events, ...processedEvents];
    console.log(`Added ${processedEvents.length} new events, total now: ${game.events.length}`);
    
    // Schedule removal of the 'isNew' flag after a delay
    setTimeout(() => {
        if (game.events) {
            game.events.forEach(event => {
                event.isNew = false;
            });
        }
    }, 5000);
};

// Helper functions
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
};

const formatTime = (seconds) => {
    if (seconds === undefined || seconds === null) return '12:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const getCurrentQuarter = (game) => {
    if (!game.quarter) return 'Q1';
    if (game.quarter <= 4) return `Q${game.quarter}`;
    return `OT${game.quarter - 4}`;
};

// Set up the simulation Web Worker
const setupSimulationWorker = () => {
    if (window.Worker) {
        simulationWorker.value = new Worker('/js/simulation-worker.js');
        
        simulationWorker.value.onmessage = (e) => {
            const data = e.data;
            
            if (data.type === 'tick') {
                // Process an update when the worker sends a tick
                if (!isUpdating.value && isSimulating.value) {
                    processUpdate();
                }
            }
        };
        
        // Start the worker if simulation is already active
        if (isSimulating.value) {
            simulationWorker.value.postMessage({ 
                command: 'start', 
                interval: 5000 // 5 seconds
            });
        }
    } else {
        console.warn('Web Workers are not supported in this browser. Falling back to setInterval.');
        
        // Fall back to setInterval if Web Workers aren't supported
        if (isSimulating.value) {
            startPeriodicUpdates();
        }
    }
};

// Start the simulation worker
const startSimulationWorker = () => {
    if (simulationWorker.value) {
        simulationWorker.value.postMessage({ 
            command: 'start', 
            interval: 5000 // 5 seconds
        });
    } else {
        // Fall back to setInterval
        startPeriodicUpdates();
    }
};

// Stop the simulation worker
const stopSimulationWorker = () => {
    if (simulationWorker.value) {
        simulationWorker.value.postMessage({ command: 'stop' });
    } else {
        stopPeriodicUpdates();
    }
};

// Reset the simulation
const resetSimulation = async () => {
    // Clear completed games and reset state
    console.log('Resetting simulation state...');
    
    try {
        // Show loading state
        isUpdating.value = true;
        
        // First, get the completed games to pass to server to avoid re-matching the same teams
        const playedMatchups = completedGames.value.map(game => ({
            home_team_id: game.homeTeam.id,
            away_team_id: game.awayTeam.id
        }));
        
        // Send request to server to generate new scheduled games for next week
        const response = await axios.post('/api/games/schedule-next-week', {
            played_matchups: playedMatchups
        });
        
        console.log('Schedule next week response:', response.data);
        
        if (response.data.success) {
            // Clear completed games after successfully scheduling new ones
            completedGames.value = [];
            selectedGames.value = [];
            selectAll.value = false;
            
            // Show success message
            alert('New games have been scheduled for the next week!');
            
            // Refresh scheduled games to display the newly created ones
            await fetchScheduledGames();
        } else {
            // Handle error from server
            console.error('Error scheduling new games:', response.data.message);
            alert('Failed to schedule new games: ' + response.data.message);
        }
    } catch (error) {
        console.error('Error scheduling new games:', error);
        alert('Error scheduling new games: ' + (error.response?.data?.message || error.message));
    } finally {
        isUpdating.value = false;
    }
};

// Helper functions for statistics
const calculateStatsPercentage = (home, away) => {
    if (!home && !away) return 50; // Default to 50% if both are 0
    
    const total = (home || 0) + (away || 0);
    if (total === 0) return 50;
    
    return Math.round((home || 0) / total * 100);
};

const calculateComparisonPercentage = (home, away) => {
    if (home === away) return 50;
    if (home === 0 && away === 0) return 50;
    if (home === 0) return 0;
    if (away === 0) return 100;
    
    const max = Math.max(home, away);
    return home > away ? Math.round((home / max) * 100) : Math.round(100 - ((away / max) * 100));
};

const calculateShotPercentage = (game, team, pointValue) => {
    if (!game) return 0;
    
    const stats = team === 'home' ? game.homeStats : game.awayStats;
    if (!stats) return 0;
    
    if (pointValue === 2) {
        const attempted = stats.twoPointsAttempted || 0;
        if (attempted === 0) return 0;
        return Math.round((stats.twoPointsMade || 0) / attempted * 100);
    } else {
        const attempted = stats.threePointsAttempted || 0;
        if (attempted === 0) return 0;
        return Math.round((stats.threePointsMade || 0) / attempted * 100);
    }
};

// Get top performing players from a game
const getTopPlayers = (playerStats, limit = 3) => {
    if (!playerStats || Object.keys(playerStats).length === 0) return [];
    
    // Convert object to array and sort by points
    const players = Object.entries(playerStats).map(([id, stats]) => ({
        id,
        ...stats
    }));
    
    // Sort by points, then assists
    return players
        .sort((a, b) => {
            if (b.points !== a.points) return b.points - a.points;
            return b.assists - a.assists;
        })
        .slice(0, limit);
};

// Update game statistics based on score changes
const updateGameStats = (game, team, points, isDevMode = false) => {
    // Initialize stats objects if they don't exist
    if (!game.homeStats) {
        game.homeStats = { 
            attacks: 0,
            assists: 0,
            twoPointsAttempted: 0,
            twoPointsMade: 0,
            threePointsAttempted: 0,
            threePointsMade: 0
        };
    }
    
    if (!game.awayStats) {
        game.awayStats = {
            attacks: 0,
            assists: 0,
            twoPointsAttempted: 0,
            twoPointsMade: 0,
            threePointsAttempted: 0,
            threePointsMade: 0
        };
    }
    
    if (!game.playerStats) {
        game.playerStats = {};
    }
    
    const teamStats = team === 'home' ? game.homeStats : game.awayStats;
    
    // Increment attack count
    teamStats.attacks = (teamStats.attacks || 0) + 1;
    
    // In dev mode, create some synthetic player stats
    if (isDevMode) {
        // Randomly decide if this was a 2-pointer or 3-pointer
        const isThreePointer = Math.random() > 0.7;
        const pointValue = isThreePointer ? 3 : 2;
        
        // Update shot stats
        if (isThreePointer) {
            teamStats.threePointsAttempted = (teamStats.threePointsAttempted || 0) + 1;
            teamStats.threePointsMade = (teamStats.threePointsMade || 0) + 1;
        } else {
            teamStats.twoPointsAttempted = (teamStats.twoPointsAttempted || 0) + 1;
            teamStats.twoPointsMade = (teamStats.twoPointsMade || 0) + 1;
        }
        
        // Randomly decide if there was an assist
        const hasAssist = Math.random() > 0.4;
        if (hasAssist) {
            teamStats.assists = (teamStats.assists || 0) + 1;
        }
        
        // Generate or update player stats
        const teamObj = team === 'home' ? game.homeTeam : game.awayTeam;
        
        // Get a random existing player or create a new one
        const playerIds = Object.keys(game.playerStats).filter(
            id => game.playerStats[id].team === teamObj.name
        );
        
        let playerId;
        if (playerIds.length > 0 && Math.random() > 0.3) {
            // Use existing player
            playerId = playerIds[Math.floor(Math.random() * playerIds.length)];
        } else {
            // Create new player
            playerId = `player-${team}-${Date.now()}`;
            game.playerStats[playerId] = {
                name: `Player ${Object.keys(game.playerStats).length + 1}`,
                team: teamObj.name,
                points: 0,
                assists: 0,
                rebounds: 0,
                fgAttempted: 0,
                fgMade: 0,
                fg: 0
            };
        }
        
        // Update the player's stats
        const player = game.playerStats[playerId];
        player.points += pointValue;
        player.fgAttempted++;
        player.fgMade++;
        player.fg = Math.round((player.fgMade / player.fgAttempted) * 100);
        
        // If there was an assist, assign it to another player
        if (hasAssist) {
            const otherPlayerIds = Object.keys(game.playerStats).filter(
                id => game.playerStats[id].team === teamObj.name && id !== playerId
            );
            
            if (otherPlayerIds.length > 0) {
                const assistPlayerId = otherPlayerIds[Math.floor(Math.random() * otherPlayerIds.length)];
                game.playerStats[assistPlayerId].assists++;
            } else {
                // Create a new player for the assist
                const assistPlayerId = `player-${team}-assist-${Date.now()}`;
                game.playerStats[assistPlayerId] = {
                    name: `Player ${Object.keys(game.playerStats).length + 1}`,
                    team: teamObj.name,
                    points: 0,
                    assists: 1,
                    rebounds: 0,
                    fgAttempted: 0,
                    fgMade: 0,
                    fg: 0
                };
            }
        }
    } else {
        // Use real game data if available
        // For now, we'll create some synthetic data similar to dev mode
        // You would replace this with actual data from the API
        
        // Placeholder for real data implementation
        const isThreePointer = Math.random() > 0.7;
        const pointValue = isThreePointer ? 3 : 2;
        
        if (isThreePointer) {
            teamStats.threePointsAttempted = (teamStats.threePointsAttempted || 0) + 1;
            teamStats.threePointsMade = (teamStats.threePointsMade || 0) + 1;
        } else {
            teamStats.twoPointsAttempted = (teamStats.twoPointsAttempted || 0) + 1;
            teamStats.twoPointsMade = (teamStats.twoPointsMade || 0) + 1;
        }
        
        // Add a player stat if none exist
        if (Object.keys(game.playerStats || {}).length === 0) {
            const teamObj = team === 'home' ? game.homeTeam : game.awayTeam;
            const playerId = `player-${team}-${Date.now()}`;
            
            game.playerStats = {
                ...game.playerStats,
                [playerId]: {
                    name: `${teamObj.name} Player`,
                    team: teamObj.name,
                    points: points,
                    assists: 0,
                    fgAttempted: 1,
                    fgMade: 1,
                    fg: 100
                }
            };
            
            // Add an assist
            teamStats.assists = (teamStats.assists || 0) + 1;
            
            const assistPlayerId = `player-${team}-assist-${Date.now()}`;
            game.playerStats[assistPlayerId] = {
                name: `${teamObj.name} Assist Player`,
                team: teamObj.name,
                points: 0,
                assists: 1,
                fgAttempted: 0,
                fgMade: 0,
                fg: 0
            };
        } else {
            // Update an existing player's stats
            const teamObj = team === 'home' ? game.homeTeam : game.awayTeam;
            const playerIds = Object.keys(game.playerStats).filter(
                id => game.playerStats[id].team === teamObj.name
            );
            
            if (playerIds.length > 0) {
                const playerId = playerIds[Math.floor(Math.random() * playerIds.length)];
                const player = game.playerStats[playerId];
                
                player.points += points;
                player.fgAttempted++;
                player.fgMade++;
                player.fg = Math.round((player.fgMade / player.fgAttempted) * 100);
                
                // Randomly update assists
                if (Math.random() > 0.6) {
                    teamStats.assists = (teamStats.assists || 0) + 1;
                    
                    const assistCandidates = playerIds.filter(id => id !== playerId);
                    if (assistCandidates.length > 0) {
                        const assistPlayerId = assistCandidates[Math.floor(Math.random() * assistCandidates.length)];
                        game.playerStats[assistPlayerId].assists++;
                    }
                }
            }
        }
    }
};

// Helper functions for formatting time
const formatGameTime = (seconds) => {
    if (!seconds && seconds !== 0) return '12:00';
    
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs < 10 ? '0' + secs : secs}`;
};

// Format a readable minute text
const minuteText = (game) => {
    if (!game || !game.minute) return 'Just started';
    
    return `Minute ${game.minute}`;
};

// Get only recent events in reverse order
const getRecentEvents = (events) => {
    if (!events || !Array.isArray(events)) return [];
    
    // Get the 5 most recent events
    return [...events].reverse().slice(0, 5);
};

// Helper function to color the winning score
const getWinnerClass = (game, team) => {
    if (!game) return '';
    
    if (team === 'home') {
        return game.homeScore > game.awayScore 
            ? 'text-green-600' 
            : game.homeScore < game.awayScore 
                ? 'text-gray-600' 
                : 'text-indigo-700';
    } else {
        return game.awayScore > game.homeScore 
            ? 'text-green-600' 
            : game.awayScore < game.homeScore 
                ? 'text-gray-600' 
                : 'text-indigo-700';
    }
};

// Get top performers across all games
const getGlobalTopPerformers = () => {
    // Create a map to consolidate player stats across all games
    const playerMap = new Map();
    
    // Combine live and completed games
    const allGames = [...liveGames.value, ...completedGames.value];
    
    // Process each game
    allGames.forEach(game => {
        if (!game.playerStats) return;
        
        // Add each player's stats to our consolidated map
        Object.entries(game.playerStats).forEach(([playerId, stats]) => {
            // Use player name and team as a unique key
            const key = `${stats.name}-${stats.team}`;
            
            if (playerMap.has(key)) {
                // Update existing player stats
                const existing = playerMap.get(key);
                existing.points += stats.points || 0;
                existing.assists += stats.assists || 0;
                existing.games += 1;
                
                // Update field goal percentage
                const totalAttempts = (existing.fgAttempted || 0) + (stats.fgAttempted || 0);
                const totalMade = (existing.fgMade || 0) + (stats.fgMade || 0);
                existing.fgAttempted = totalAttempts;
                existing.fgMade = totalMade;
                existing.fg = totalAttempts > 0 ? Math.round((totalMade / totalAttempts) * 100) : 0;
            } else {
                // Add new player
                playerMap.set(key, {
                    id: playerId,
                    name: stats.name,
                    team: stats.team,
                    points: stats.points || 0,
                    assists: stats.assists || 0,
                    fgAttempted: stats.fgAttempted || 0,
                    fgMade: stats.fgMade || 0,
                    fg: stats.fg || 0,
                    games: 1
                });
            }
        });
    });
    
    // Convert map to array
    const players = Array.from(playerMap.values());
    
    // Find top scorer and assist leader
    const topScorer = players.length > 0 
        ? players.reduce((top, player) => player.points > top.points ? player : top, players[0])
        : null;
        
    const topAssist = players.length > 0 
        ? players.reduce((top, player) => player.assists > top.assists ? player : top, players[0])
        : null;
    
    // Get top 5 players by points and assists
    const topScorers = [...players].sort((a, b) => b.points - a.points).slice(0, 5);
    const topAssists = [...players].sort((a, b) => b.assists - a.assists).slice(0, 5);
    
    return {
        topScorer,
        topAssist,
        topScorers,
        topAssists,
        allPlayers: players.sort((a, b) => b.points - a.points)
    };
};

// Helper function to check if there's a "hot" player in a game
const getHotPlayer = (game) => {
    if (!game || !game.playerStats || Object.keys(game.playerStats).length === 0) {
        return null;
    }
    
    // Get all players in this game
    const players = Object.entries(game.playerStats).map(([id, stats]) => ({
        id,
        ...stats
    }));
    
    // Hot player criteria (these thresholds can be adjusted)
    const hotPlayers = players.filter(player => {
        return (player.points >= 15) || // High scorer
               (player.assists >= 5) || // Good playmaker
               (player.points >= 10 && player.assists >= 3); // Good all-around performance
    });
    
    // Sort by "hotness" - combine points and assists with a multiplier
    if (hotPlayers.length > 0) {
        return hotPlayers.sort((a, b) => {
            const aHotness = a.points + (a.assists * 2);
            const bHotness = b.points + (b.assists * 2);
            return bHotness - aHotness;
        })[0]; // Return the hottest player
    }
    
    return null;
};

// Get descriptive text for hot player
const getHotPlayerStat = (player) => {
    if (!player) return '';
    
    if (player.points >= 15 && player.assists >= 5) {
        return `${player.points} PTS, ${player.assists} AST - Dominating!`;
    } else if (player.points >= 15) {
        return `${player.points} PTS - Unstoppable scorer!`;
    } else if (player.assists >= 5) {
        return `${player.assists} AST - Elite playmaker!`;
    } else {
        return `${player.points} PTS, ${player.assists} AST`;
    }
};
</script> 