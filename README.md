# Moontech Backend Task

Laravel REST API backend with order management and admin capabilities.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) (with Compose plugin)
- [Git](https://git-scm.com/)

## Quick Start

```bash
# 1. Clone & enter the project
git clone <repo-url> moontech-backend-task
cd moontech-backend-task

# 2. Copy environment file
cp .env.example .env

# 3. Start containers (builds images, starts MySQL, app, queue worker, nginx)
docker compose up -d

# 4. Install PHP dependencies
docker compose exec app composer install

# 5. Generate app key
docker compose exec app php artisan key:generate

# 6. Run database migrations & seeders
docker compose exec app php artisan migrate --seed

# 7. Done! The API is available at:
#    http://localhost:8080
```

## Services

| Service        | Container        | Description                        |
|----------------|------------------|------------------------------------|
| **Nginx**      | `moontech-nginx` | Web server (port 8080)             |
| **PHP-FPM**    | `moontech-app`   | PHP application server             |
| **MySQL**      | `moontech-mysql` | Database (host port 3307)          |
| **Queue**      | `moontech-queue` | Queue worker for async jobs        |

## Useful Commands

```bash
# View running containers
docker compose ps

# Follow logs
docker compose logs -f

# Run artisan commands
docker compose exec app php artisan <command>

# Run tests
docker compose exec app php artisan test

# Run a specific test
docker compose exec app php artisan test --filter=OrderStatusTest

# Access MySQL CLI
docker compose exec mysql mysql -u laravel -p laravel

# Stop containers
docker compose down

# Stop and remove volumes (wipes database)
docker compose down -v

# Rebuild images after Dockerfile changes
docker compose build
```

## API Endpoints

### Authentication

| Method | Endpoint             | Description         |
|--------|----------------------|---------------------|
| POST   | `/api/register`      | Register a new user |
| POST   | `/api/login`         | Login               |
| POST   | `/api/logout`        | Logout              |

### Orders

| Method | Endpoint                     | Description              |
|--------|------------------------------|--------------------------|
| GET    | `/api/orders`                | List user's orders       |
| POST   | `/api/orders`                | Create an order          |
| PATCH  | `/api/admin/orders/{id}/status` | Update order status (admin) |

### Admin

| Method | Endpoint                | Description            |
|--------|-------------------------|------------------------|
| GET    | `/api/admin/orders`     | List all orders        |
| GET    | `/api/admin/products`   | List all products      |
| POST   | `/api/admin/products`   | Create a product       |
| PUT    | `/api/admin/products/{id}` | Update a product    |
| DELETE | `/api/admin/products/{id}` | Delete a product    |

## Seeded Credentials

| Role  | Phone          | Password   |
|-------|----------------|------------|
| Admin | 123123123123   | password   |
| User  | 01001234567    | password   |
