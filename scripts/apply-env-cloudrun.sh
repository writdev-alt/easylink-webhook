#!/bin/bash

# Apply Environment Variables to Cloud Run from .env.cloudrun file
# Usage: ./scripts/apply-env-cloudrun.sh [project-id] [region] [service-name] [env-file]

set -e

PROJECT_ID=${1:-""}
REGION=${2:-"asia-southeast1"}
SERVICE_NAME=${3:-"webhook"}
ENV_FILE=${4:-".env.cloudrun"}

# Check if project ID is provided
if [ -z "$PROJECT_ID" ]; then
    echo "Error: Project ID is required"
    echo "Usage: ./apply-env-cloudrun.sh [project-id] [region] [service-name] [env-file]"
    echo "Example: ./apply-env-cloudrun.sh my-project-id asia-southeast1 webhook .env.cloudrun"
    exit 1
fi

# Check if .env.cloudrun file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: Environment file '$ENV_FILE' not found!"
    exit 1
fi

echo "📋 Applying environment variables from $ENV_FILE to Cloud Run service"
echo "Project ID: $PROJECT_ID"
echo "Region: $REGION"
echo "Service: $SERVICE_NAME"
echo ""

# Check if service exists
echo "Checking if Cloud Run service exists..."
if ! gcloud run services describe $SERVICE_NAME --region $REGION --project $PROJECT_ID >/dev/null 2>&1; then
    echo "Error: Cloud Run service '$SERVICE_NAME' not found in region '$REGION'!" >&2
    echo ""
    echo "Please deploy the service first using:"
    echo "./scripts/deploy-cloudrun.sh $PROJECT_ID $REGION"
    echo ""
    exit 1
fi

echo "✅ Service found, proceeding with environment variable update..."
echo ""

# Parse .env file and create env vars string
ENV_VARS=""

while IFS= read -r line || [ -n "$line" ]; do
    # Skip empty lines and comments
    if [[ "$line" =~ ^[[:space:]]*# ]] || [[ -z "$line" ]]; then
        continue
    fi
    
    # Parse KEY=VALUE format
    if [[ "$line" =~ ^([^=]+)=(.*)$ ]]; then
        KEY="${BASH_REMATCH[1]}"
        VALUE="${BASH_REMATCH[2]}"
        
        # Remove quotes if present
        VALUE=$(echo "$VALUE" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
        
        # Skip if value is empty after removing quotes
        if [ -z "$VALUE" ]; then
            continue
        fi
        
        # Add to env vars string
        if [ -z "$ENV_VARS" ]; then
            ENV_VARS="$KEY=$VALUE"
        else
            ENV_VARS="$ENV_VARS,$KEY=$VALUE"
        fi
    fi
done < "$ENV_FILE"

if [ -z "$ENV_VARS" ]; then
    echo "Error: No environment variables found in $ENV_FILE"
    exit 1
fi

echo "Updating Cloud Run service with environment variables..."
gcloud run services update $SERVICE_NAME \
    --region $REGION \
    --update-env-vars "$ENV_VARS" \
    --project $PROJECT_ID

if [ $? -ne 0 ]; then
    echo "Error: Failed to update environment variables"
    exit 1
fi

echo ""
echo "✅ Environment variables applied successfully!"
echo ""

