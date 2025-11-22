# Google Cloud Run Deployment Guide

This guide explains how to deploy the Laravel application to Google Cloud Run with the service name "webhook".

## Prerequisites

1. **Google Cloud SDK (gcloud)** installed and configured
2. **Google Cloud Project** with billing enabled
3. **Docker** installed locally (optional, for testing)

## Quick Deploy

### Option 1: Using the Deployment Script (Recommended)

```bash
# Make the script executable (Linux/Mac)
chmod +x deploy-cloudrun.sh

# Deploy to Cloud Run (automatically connects to Cloud SQL instance "base-image")
./deploy-cloudrun.sh YOUR_PROJECT_ID us-central1

# Or specify a different Cloud SQL instance
./deploy-cloudrun.sh YOUR_PROJECT_ID us-central1 base-image
```

**Note:** The script automatically configures Cloud SQL connection for the `base-image` instance. Make sure:
- The Cloud SQL instance exists and is accessible from the Cloud Run region
- Database credentials are set via environment variables or Secret Manager

### Option 2: Manual Deployment

1. **Set your project ID:**
   ```bash
   gcloud config set project YOUR_PROJECT_ID
   ```

2. **Enable required APIs:**
   ```bash
   gcloud services enable cloudbuild.googleapis.com
   gcloud services enable run.googleapis.com
   gcloud services enable containerregistry.googleapis.com
   ```

3. **Build and deploy with Cloud SQL:**
   ```bash
   gcloud builds submit --tag gcr.io/YOUR_PROJECT_ID/webhook --file Dockerfile.cloudrun
   
   # Deploy with Cloud SQL connection (base-image instance)
   gcloud run deploy webhook \
     --image gcr.io/YOUR_PROJECT_ID/webhook:latest \
     --platform managed \
     --region us-central1 \
     --allow-unauthenticated \
     --port 8080 \
     --memory 512Mi \
     --cpu 1 \
     --add-cloudsql-instances YOUR_PROJECT_ID:us-central1:base-image \
     --set-env-vars DB_HOST=/cloudsql/YOUR_PROJECT_ID:us-central1:base-image
   ```

### Option 3: Using Cloud Build (CI/CD)

1. **Connect your repository** to Cloud Build (if using a Git repository)

2. **Trigger a build:**
   ```bash
   gcloud builds submit --config cloudbuild.yaml
   ```

## Configuration

### Environment Variables

Set environment variables using the Cloud Run console or gcloud CLI:

```bash
# Database name "base" is set by default
gcloud run services update webhook \
  --region us-central1 \
  --update-env-vars \
    APP_ENV=production,\
    APP_DEBUG=false,\
    DB_HOST=/cloudsql/PROJECT_ID:REGION:base-image,\
    DB_DATABASE=base,\
    DB_USERNAME=YOUR_USERNAME,\
    DB_PASSWORD=YOUR_PASSWORD
```

### Database Connection

The project includes a Cloud SQL instance named **`base-image`** with a database named **`base`**. The deployment script automatically configures the connection.

**For Cloud SQL (Unix Socket Connection - Recommended):**

The deployment script automatically sets up the Cloud SQL connection. For manual configuration:

```bash
# Replace PROJECT_ID and REGION with your values
gcloud run services update webhook \
  --region us-central1 \
  --add-cloudsql-instances PROJECT_ID:REGION:base-image \
  --update-env-vars DB_HOST=/cloudsql/PROJECT_ID:REGION:base-image,DB_DATABASE=base
```

**For Cloud SQL (Private IP Connection):**

If using private IP, set the DB_HOST to the private IP address:

```bash
gcloud run services update webhook \
  --region us-central1 \
  --vpc-connector CONNECTOR_NAME \
  --update-env-vars DB_HOST=PRIVATE_IP_ADDRESS
```

**Using the deployment script with Cloud SQL:**

```bash
# The script automatically connects to base-image instance with database "base"
./deploy-cloudrun.sh YOUR_PROJECT_ID us-central1

# Or specify a different Cloud SQL instance (database name defaults to "base")
./deploy-cloudrun.sh YOUR_PROJECT_ID us-central1 base-image
```

**Note:** The database name is set to **`base`** by default, which matches the existing database in your Cloud SQL instance.

**Setting Database Credentials:**

Store database credentials in Secret Manager and reference them:

```bash
# Create secrets for database credentials (database name is "base")
echo -n "base" | gcloud secrets create db-database --data-file=-
echo -n "your-username" | gcloud secrets create db-username --data-file=-
echo -n "your-password" | gcloud secrets create db-password --data-file=-

# Grant Cloud Run service account access
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format="value(projectNumber)")
gcloud secrets add-iam-policy-binding db-database \
  --member=serviceAccount:$PROJECT_NUMBER-compute@developer.gserviceaccount.com \
  --role=roles/secretmanager.secretAccessor
gcloud secrets add-iam-policy-binding db-username \
  --member=serviceAccount:$PROJECT_NUMBER-compute@developer.gserviceaccount.com \
  --role=roles/secretmanager.secretAccessor
gcloud secrets add-iam-policy-binding db-password \
  --member=serviceAccount:$PROJECT_NUMBER-compute@developer.gserviceaccount.com \
  --role=roles/secretmanager.secretAccessor

# Update Cloud Run service to use secrets
gcloud run services update webhook \
  --region us-central1 \
  --update-secrets DB_DATABASE=db-database:latest,DB_USERNAME=db-username:latest,DB_PASSWORD=db-password:latest
```

### Secrets Management

Store sensitive data in Secret Manager:

```bash
# Create a secret
echo -n "your-secret-value" | gcloud secrets create db-password --data-file=-

# Grant access to Cloud Run
gcloud secrets add-iam-policy-binding db-password \
  --member=serviceAccount:PROJECT_NUMBER-compute@developer.gserviceaccount.com \
  --role=roles/secretmanager.secretAccessor

# Update service to use secret
gcloud run services update webhook \
  --region us-central1 \
  --update-secrets DB_PASSWORD=db-password:latest
```

## Service Configuration

The default configuration includes:

- **Service Name**: `webhook`
- **Port**: `8080` (Cloud Run sets `PORT` env variable)
- **Memory**: `512Mi`
- **CPU**: `1`
- **Min Instances**: `0` (can scale to zero)
- **Max Instances**: `10`
- **Timeout**: `300` seconds
- **Concurrency**: `80` requests per instance

To modify these settings:

```bash
gcloud run services update webhook \
  --region us-central1 \
  --memory 1Gi \
  --cpu 2 \
  --min-instances 1 \
  --max-instances 20
```

## Health Checks

Cloud Run automatically performs health checks on your service. Ensure your application responds to HTTP requests on the configured port.

## Monitoring and Logs

View logs:
```bash
gcloud run services logs read webhook --region us-central1
```

Monitor in Cloud Console:
```bash
gcloud run services describe webhook --region us-central1
```

## Updating the Service

To update the service with a new image:

```bash
# Rebuild and push
gcloud builds submit --tag gcr.io/YOUR_PROJECT_ID/webhook --file Dockerfile.cloudrun

# Update the service
gcloud run services update webhook \
  --image gcr.io/YOUR_PROJECT_ID/webhook:latest \
  --region us-central1
```

## Custom Domain

To use a custom domain:

1. **Map your domain:**
   ```bash
   gcloud run domain-mappings create \
     --service webhook \
     --domain your-domain.com \
     --region us-central1
   ```

2. **Update DNS** records as instructed

## Troubleshooting

### Port Configuration
Cloud Run automatically sets the `PORT` environment variable. The Dockerfile handles this dynamically.

### Database Connection Issues
- Ensure Cloud SQL Proxy is configured correctly
- Check firewall rules allow connections
- Verify database credentials in Secret Manager

### Build Failures
- Check Cloud Build logs: `gcloud builds list`
- Verify Dockerfile.cloudrun is correct
- Ensure all required files are present

### Runtime Errors
- View logs: `gcloud run services logs read webhook --region us-central1`
- Check environment variables are set correctly
- Verify database connectivity

## Cost Optimization

- **Set min-instances to 0** for development (saves costs when idle)
- **Use appropriate memory** (512Mi is sufficient for most Laravel apps)
- **Configure max-instances** based on expected traffic
- **Use Cloud SQL Proxy** for database connections to avoid external IP costs

## Security Best Practices

1. **Use Secret Manager** for sensitive data (passwords, API keys)
2. **Enable VPC connector** for private network access
3. **Use IAM** to control access to the service
4. **Enable binary authorization** for production
5. **Regularly update** your base images and dependencies

## Next Steps

- Set up **Cloud SQL** for production database
- Configure **Cloud Storage** for file uploads
- Set up **Cloud CDN** for static assets
- Configure **monitoring and alerting**
- Set up **CI/CD pipeline** with Cloud Build

