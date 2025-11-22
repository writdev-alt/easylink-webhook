# Worker WRPay

Laravel application for payment processing and webhook management.

## Documentation

All documentation is available in the [`docs/`](./docs/) directory:

- **[README.md](./docs/README.md)** - Project overview and setup
- **[DOCKER.md](./docs/DOCKER.md)** - Docker setup with FrankenPHP
- **[DEPLOY-CLOUDRUN.md](./docs/DEPLOY-CLOUDRUN.md)** - Google Cloud Run deployment guide
- **[CHANGELOG.md](./docs/CHANGELOG.md)** - Project changelog

## Quick Start

### Local Development with Docker

```bash
docker-compose up -d --build
```

See [docs/DOCKER.md](./docs/DOCKER.md) for detailed instructions.

### Deploy to Google Cloud Run

```bash
# PowerShell
.\scripts\deploy-cloudrun.ps1 -ProjectId "your-project-id"

# Bash
./scripts/deploy-cloudrun.sh your-project-id asia-southeast1
```

See [docs/DEPLOY-CLOUDRUN.md](./docs/DEPLOY-CLOUDRUN.md) for detailed deployment instructions.

## Scripts

Deployment and management scripts are located in the [`scripts/`](./scripts/) directory.

## Requirements

- PHP 8.4+
- Laravel 12.0
- MySQL 8.0 or Cloud SQL
- Redis (optional, for queues and caching)
- Elasticsearch (optional)

