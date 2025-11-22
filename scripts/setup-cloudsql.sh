#!/bin/bash

# Cloud SQL Setup Script for base-image instance
# This script helps set up the Cloud SQL connection and credentials

set -e

PROJECT_ID=${1:-""}
REGION=${2:-"asia-southeast1"}
INSTANCE_NAME=${3:-"base-image"}
SERVICE_NAME="webhook"

if [ -z "$PROJECT_ID" ]; then
    echo "Error: Project ID is required"
    echo "Usage: ./setup-cloudsql.sh [project-id] [region] [instance-name]"
    echo "Example: ./setup-cloudsql.sh my-project-id asia-southeast1 base-image"
    exit 1
fi

echo "🔧 Setting up Cloud SQL connection for base-image"
echo "Project ID: $PROJECT_ID"
echo "Region: $REGION"
echo "Instance: $INSTANCE_NAME"
echo ""

# Set the project
gcloud config set project $PROJECT_ID

# Get Cloud SQL instance connection name
CONNECTION_NAME="${PROJECT_ID}:${REGION}:${INSTANCE_NAME}"

echo "📋 Cloud SQL Connection Details:"
echo "Connection Name: $CONNECTION_NAME"
echo "Unix Socket Path: /cloudsql/$CONNECTION_NAME"
echo "Database Name: base"
echo ""

# Check if Cloud SQL instance exists
echo "Checking Cloud SQL instance..."
if gcloud sql instances describe $INSTANCE_NAME --format="value(name)" >/dev/null 2>&1; then
    echo "✅ Cloud SQL instance '$INSTANCE_NAME' found"
    
    # Get instance information
    INSTANCE_IP=$(gcloud sql instances describe $INSTANCE_NAME --format="value(ipAddresses[0].ipAddress)")
    DATABASE_VERSION=$(gcloud sql instances describe $INSTANCE_NAME --format="value(databaseVersion)")
    
    echo "Instance IP: $INSTANCE_IP"
    echo "Database Version: $DATABASE_VERSION"
    echo ""
    
    # Get list of databases
    echo "📊 Databases in instance:"
    gcloud sql databases list --instance=$INSTANCE_NAME || echo "Could not list databases. Make sure you have permissions."
    echo ""
    
    # Get list of users
    echo "👤 Users in instance:"
    gcloud sql users list --instance=$INSTANCE_NAME || echo "Could not list users. Make sure you have permissions."
    echo ""
    
else
    echo "⚠️  Cloud SQL instance '$INSTANCE_NAME' not found in region '$REGION'"
    echo ""
    echo "To create a new Cloud SQL instance:"
    echo "gcloud sql instances create $INSTANCE_NAME \\"
    echo "  --database-version=MYSQL_8_0 \\"
    echo "  --tier=db-f1-micro \\"
    echo "  --region=$REGION"
    echo ""
    exit 1
fi

# Get Cloud Run service account
echo "🔐 Configuring Cloud Run service account..."
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format="value(projectNumber)")
SERVICE_ACCOUNT="${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"

echo "Cloud Run Service Account: $SERVICE_ACCOUNT"
echo ""

# Add Cloud SQL Client role to service account
echo "Granting Cloud SQL Client role to Cloud Run service account..."
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:$SERVICE_ACCOUNT" \
    --role="roles/cloudsql.client" \
    --condition=None

echo "✅ Cloud Run service account now has Cloud SQL Client permissions"
echo ""

# Instructions for database credentials
echo "📝 Next Steps:"
echo ""
echo "1. Set database credentials as environment variables or secrets:"
echo ""
echo "   Option A: Environment Variables (for testing):"
echo "   # Database name 'base' is already set by default"
echo "   gcloud run services update $SERVICE_NAME \\"
echo "     --region $REGION \\"
echo "     --update-env-vars DB_DATABASE=base,DB_USERNAME=your_username,DB_PASSWORD=your_password"
echo ""
echo "   Option B: Secret Manager (recommended for production):"
echo "   # Create secrets (database name is 'base')"
echo "   echo -n 'base' | gcloud secrets create db-database --data-file=-"
echo "   echo -n 'your_username' | gcloud secrets create db-username --data-file=-"
echo "   echo -n 'your_password' | gcloud secrets create db-password --data-file=-"
echo ""
echo "   # Grant access"
echo "   gcloud secrets add-iam-policy-binding db-database \\"
echo "     --member=serviceAccount:$SERVICE_ACCOUNT \\"
echo "     --role=roles/secretmanager.secretAccessor"
echo "   # Repeat for db-username and db-password"
echo ""
echo "   # Update service"
echo "   gcloud run services update $SERVICE_NAME \\"
echo "     --region $REGION \\"
echo "     --update-secrets DB_DATABASE=db-database:latest,DB_USERNAME=db-username:latest,DB_PASSWORD=db-password:latest"
echo ""
echo "2. The Cloud Run service should already have Cloud SQL connection configured."
echo "   DB_HOST should be set to: /cloudsql/$CONNECTION_NAME"
echo "   DB_DATABASE should be set to: base"
echo ""
echo "3. Verify the connection:"
echo "   gcloud run services describe $SERVICE_NAME --region $REGION --format='value(spec.template.spec.containers[0].env)'"
echo ""

