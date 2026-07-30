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
docker compose exec app tail -f storage/logs/laravel.log

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

## API Documentation (Postman)

A complete Postman v2.1 collection (`MoonTech-API.postman_collection.json`) is included in the project root with all 19 endpoints organized into 6 folders.

### Importing

Open Postman → **File → Import** (or drag & drop `MoonTech-API.postman_collection.json`).

### Getting Started

1. Create an environment with `base_url` set to `http://localhost:8080/api`
2. Send **Register** or **Login** — store the token automatically with a test script:
   ```js
   var jsonData = pm.response.json();
   pm.collectionVariables.set("token", jsonData.data.token);
   ```
3. All protected requests inherit `{{token}}` from the collection-level `Authorization` header.
4. Set `{{product_id}}`, `{{order_id}}`, `{{notification_id}}` from response data as you go.

### OTP / Verification Codes

During development OTPs are logged to file instead of being sent via SMS. View them with:

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

Look for log entries containing the OTP value and use them with `/api/phone/verify` or `/api/reset-password`.

## API Endpoints

### Authentication

Register, login, logout, phone verification, and password reset.

| Method | Endpoint                  | Description                           |
|--------|---------------------------|---------------------------------------|
| POST   | `/api/register`           | Register (name, phone, password)      |
| POST   | `/api/login`              | Login (phone, password) → Bearer token|
| POST   | `/api/logout`             | Revoke current token                  |
| GET    | `/api/user`               | Fetch authenticated user profile      |
| POST   | `/api/phone/verify`       | Verify phone with OTP                 |
| POST   | `/api/phone/resend`       | Resend verification OTP               |
| POST   | `/api/forgot-password`    | Request password reset OTP            |
| POST   | `/api/reset-password`     | Reset password with OTP               |

### Products (Admin)

Full CRUD for products. Image upload supported (optional, max 2 MB).

| Method | Endpoint                        | Description              |
|--------|---------------------------------|--------------------------|
| GET    | `/api/admin/products`           | List all products        |
| POST   | `/api/admin/products`           | Create a product         |
| GET    | `/api/admin/products/{id}`      | Show a product           |
| PUT    | `/api/admin/products/{id}`      | Update a product         |
| DELETE | `/api/admin/products/{id}`      | Delete a product         |

### Stock Subscription

Users subscribe to be notified when an out-of-stock product is restocked. Idempotent — duplicate subscriptions are silently handled.

| Method | Endpoint                               | Description                       |
|--------|----------------------------------------|-----------------------------------|
| POST   | `/api/products/{product}/notify-me`    | Subscribe to back-in-stock alert  |

### Orders (User)

Authenticated users create orders and view their own order history. Stock is validated and deducted atomically inside a database transaction.

| Method | Endpoint           | Description               |
|--------|--------------------|---------------------------|
| GET    | `/api/orders`      | List authenticated user's orders |
| POST   | `/api/orders`      | Create an order           |

### Orders (Admin)

Admins list all orders and update order statuses. Status change creates a history record and dispatches a notification to the order owner. Valid transitions: `pending→confirmed|cancelled`, `confirmed→processing|cancelled`, `processing→shipped|cancelled`, `shipped→delivered`. Terminal statuses: `delivered`, `cancelled`.

| Method | Endpoint                              | Description                 |
|--------|---------------------------------------|-----------------------------|
| GET    | `/api/admin/orders`                   | List all orders (with user) |
| PATCH  | `/api/admin/orders/{order}/status`    | Update order status         |

### Notifications (User)

View and manage database notifications (order status changes, back-in-stock alerts).

| Method | Endpoint                              | Description                         |
|--------|---------------------------------------|-------------------------------------|
| GET    | `/api/notifications`                  | Paginated list of user notifications|
| PATCH  | `/api/notifications/{notification}/read` | Mark a notification as read     |

## Seeded Credentials

| Role  | Phone          | Password   |
|-------|----------------|------------|
| Admin | 01001234567   | password   |
| User  | 01101234567    | password   |

