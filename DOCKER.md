# Docker Setup with FrankenPHP

This project is configured to run with Docker and FrankenPHP.

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)
- At least 4GB of available RAM

## Quick Start

1. **Build and start the containers:**
   ```bash
   docker-compose up -d --build
   ```

2. **Set up your environment:**
   Create a `.env` file in the project root with the following variables:
   ```env
   APP_NAME=WorkerWrPay
   APP_ENV=local
   APP_DEBUG=true
   APP_KEY=

   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   DB_DATABASE=worker_wrpay
   DB_USERNAME=worker_wrpay
   DB_PASSWORD=password

   QUEUE_CONNECTION=database
   OCTANE_SERVER=frankenphp
   OCTANE_WORKERS=4
   ```

3. **Generate application key (if not auto-generated):**
   ```bash
   docker-compose exec app php artisan key:generate
   ```

4. **Run migrations:**
   ```bash
   docker-compose exec app php artisan migrate
   ```

5. **Access the application:**
   - Application: http://localhost:8000
   - MySQL: localhost:3306
   - Elasticsearch: http://localhost:9200

## Services

- **app**: Laravel application running on FrankenPHP (port 8000)
- **queue**: Queue worker processing jobs from the `webhooks` and `default` queues
- **mysql**: MySQL 8.0 database server (port 3306)
- **elasticsearch**: Elasticsearch 8.11.0 (ports 9200, 9300)

## Useful Commands

### View logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f queue
```

### Execute Artisan commands
```bash
docker-compose exec app php artisan <command>
```

### Access container shell
```bash
docker-compose exec app sh
```

### Stop containers
```bash
docker-compose down
```

### Stop and remove volumes (clean slate)
```bash
docker-compose down -v
```

### Rebuild containers
```bash
docker-compose up -d --build
```

### Install/update Composer dependencies
```bash
docker-compose exec app composer install
docker-compose exec app composer update
```

### Run tests
```bash
docker-compose exec app php artisan test
```

## Development

The Docker setup is configured for development with:
- Volume mounts for live code changes
- Hot reloading via FrankenPHP
- Separate queue worker container

## Production Considerations

For production deployment:
1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Use `composer install --no-dev --optimize-autoloader` in Dockerfile
3. Configure proper database credentials
4. Set up proper SSL/TLS certificates
5. Use environment-specific configuration
6. Consider using a reverse proxy (nginx/traefik) in front of FrankenPHP

## Troubleshooting

### Database connection issues
- Ensure MySQL container is healthy: `docker-compose ps`
- Check database credentials in `.env`
- Wait for database to be ready (entrypoint script handles this)

### Permission issues
- Storage and cache directories should be writable
- The entrypoint script sets proper permissions

### Queue not processing
- Check queue worker logs: `docker-compose logs queue`
- Ensure queue worker container is running: `docker-compose ps`
- Verify queue connection in `.env`

