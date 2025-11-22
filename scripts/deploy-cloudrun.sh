#!/bin/bash

# Google Cloud Run Deployment Script
# Usage: ./scripts/deploy-cloudrun.sh [project-id] [region] [cloud-sql-instance]

set -e

PROJECT_ID=${1:-""}
REGION=${2:-"asia-southeast1"}
CLOUD_SQL_INSTANCE=${3:-"base-image"}
SERVICE_NAME="webhook"
IMAGE_NAME="gcr.io/${PROJECT_ID}/${SERVICE_NAME}"
CLOUD_SQL_CONNECTION_NAME="${PROJECT_ID}:${REGION}:${CLOUD_SQL_INSTANCE}"

# Check if project ID is provided
if [ -z "$PROJECT_ID" ]; then
    echo "Error: Project ID is required"
    echo "Usage: ./deploy-cloudrun.sh [project-id] [region] [cloud-sql-instance]"
    echo "Example: ./deploy-cloudrun.sh my-project-id asia-southeast1 base-image"
    exit 1
fi

echo "🚀 Deploying to Google Cloud Run"
echo "Project ID: $PROJECT_ID"
echo "Region: $REGION"
echo "Service: $SERVICE_NAME"
echo "Cloud SQL Instance: $CLOUD_SQL_INSTANCE"
echo "Cloud SQL Connection: $CLOUD_SQL_CONNECTION_NAME"
echo "Database Name: base"
echo ""

# Set the project
echo "Setting GCP project..."
gcloud config set project $PROJECT_ID

# Enable required APIs
echo "Enabling required APIs..."
gcloud services enable cloudbuild.googleapis.com
gcloud services enable run.googleapis.com
gcloud services enable containerregistry.googleapis.com
gcloud services enable sqladmin.googleapis.com

# Build the Docker image
echo "Building Docker image..."
gcloud builds submit --config cloudbuild-build.yaml --substitutions "_IMAGE_NAME=$IMAGE_NAME"

# Deploy to Cloud Run with Cloud SQL connection
echo "Deploying to Cloud Run with Cloud SQL connection..."
gcloud run deploy $SERVICE_NAME \
    --image ${IMAGE_NAME}:latest \
    --platform managed \
    --region $REGION \
    --allow-unauthenticated \
    --port 8080 \
    --memory 512Mi \
    --cpu 1 \
    --min-instances 0 \
    --max-instances 10 \
    --timeout 300 \
    --concurrency 80 \
    --add-cloudsql-instances $CLOUD_SQL_CONNECTION_NAME \
    --set-env-vars "APP_ENV=production,DB_HOST=/cloudsql/$CLOUD_SQL_CONNECTION_NAME,DB_DATABASE=base" \
    --project $PROJECT_ID

# Get the service URL
echo ""
echo "✅ Deployment complete!"
echo ""
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME --region $REGION --format 'value(status.url)')
echo "Service URL: $SERVICE_URL"
echo ""
echo "To update environment variables:"
echo "gcloud run services update $SERVICE_NAME --region $REGION --update-env-vars KEY=VALUE"
echo ""
echo "To configure Cloud SQL credentials, run:"
echo "./scripts/setup-cloudsql.sh $PROJECT_ID $REGION $CLOUD_SQL_INSTANCE"

