# NBA Simulator

A real-time NBA simulation web application built with Laravel 12, Vue.js, and PostgreSQL.

## Project Description

This application simulates NBA games in real-time with the following features:
- Simulates a week of NBA fixtures
- All matches start simultaneously and last 48 minutes (simulated as 5 seconds = 1 minute)
- Each match ends in 240 seconds total
- Shows live statistics including attack count, total scores, player-based assists, and success rates
- Updates the league table after matches are complete

## Technologies Used

- Laravel 12
- PHP 8
- Vue.js
- PostgreSQL
- Docker

## Installation

### Prerequisites

- Docker and Docker Compose

### Setup

1. Clone the repository:
```bash
git clone <git@github.com:hparsi/nba-simulator.git>
cd nba-simulator
```

2. Create a `.env` file from the example:
```bash
cp .env.example .env
```

3. Start the Docker containers:
```bash
docker-compose up -d
```

4. Install Laravel dependencies:
```bash
docker-compose exec app composer install
```

5. Generate application key:
```bash
docker-compose exec app php artisan key:generate
```

6. Run database migrations:
```bash
docker-compose exec app php artisan migrate
```

7. Seed the database with teams and players:
```bash
docker-compose exec app php artisan db:seed
```

8. Access the application:
```bash
http://localhost:8090/simulation/live
```


## Commands

- Start containers: `docker-compose up -d`
- Stop containers: `docker-compose down`
- View logs: `docker-compose logs -f`
- Access PHP container: `docker-compose exec app bash`
- Access PostgreSQL: `docker-compose exec db psql -U postgres -d nba_simulator`

## Development Workflow

1. The Laravel backend is in the root directory
2. Vue.js files are located in the `resources/js` directory
3. To compile assets during development: `docker-compose exec node npm run dev`
4. For production build: `docker-compose exec node npm run build`

## Features

- Real-time game simulation
- Live score updates
- Player statistics tracking
- Team standings
- Advanced scheduling with team rotation (no rematches in the same week)
- Attack count
- Player based assists
- Player based 2 or 3 points success rate
- Real time top scorer and assist

## Contributors

- [Maziyarr Parsi]