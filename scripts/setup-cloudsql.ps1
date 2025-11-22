# Cloud SQL Setup Script for base-image instance (PowerShell)
# This script helps set up the Cloud SQL connection and credentials
# Usage: .\scripts\setup-cloudsql.ps1 [-ProjectId] <project-id> [-Region] <region> [-InstanceName] <instance-name>

param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$ProjectId,
    
    [Parameter(Mandatory=$false, Position=1)]
    [string]$Region = "asia-southeast1"
    
    [Parameter(Mandatory=$false, Position=2)]
    [string]$InstanceName = "base-image"
)

# Set error action preference
$ErrorActionPreference = "Stop"

$ServiceName = "webhook"

Write-Host "🔧 Setting up Cloud SQL connection for base-image" -ForegroundColor Cyan
Write-Host "Project ID: $ProjectId"
Write-Host "Region: $Region"
Write-Host "Instance: $InstanceName"
Write-Host ""

# Set the project
gcloud config set project $ProjectId
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to set GCP project" -ForegroundColor Red
    exit 1
}

# Get Cloud SQL instance connection name
$ConnectionName = "$ProjectId`:$Region`:$InstanceName"

Write-Host "📋 Cloud SQL Connection Details:" -ForegroundColor Yellow
Write-Host "Connection Name: $ConnectionName"
Write-Host "Unix Socket Path: /cloudsql/$ConnectionName"
Write-Host "Database Name: base"
Write-Host ""

# Check if Cloud SQL instance exists
Write-Host "Checking Cloud SQL instance..." -ForegroundColor Yellow
try {
    $instanceInfo = gcloud sql instances describe $InstanceName --format="json" 2>&1 | ConvertFrom-Json
    Write-Host "✅ Cloud SQL instance '$InstanceName' found" -ForegroundColor Green
    
    # Get instance information
    $instanceIp = $instanceInfo.ipAddresses | Where-Object { $_.type -eq "PRIMARY" } | Select-Object -First 1 -ExpandProperty ipAddress
    $databaseVersion = $instanceInfo.databaseVersion
    
    Write-Host "Instance IP: $instanceIp"
    Write-Host "Database Version: $databaseVersion"
    Write-Host ""
    
    # Get list of databases
    Write-Host "📊 Databases in instance:" -ForegroundColor Yellow
    gcloud sql databases list --instance=$InstanceName 2>&1
    Write-Host ""
    
    # Get list of users
    Write-Host "👤 Users in instance:" -ForegroundColor Yellow
    gcloud sql users list --instance=$InstanceName 2>&1
    Write-Host ""
    
} catch {
    Write-Host "⚠️  Cloud SQL instance '$InstanceName' not found in region '$Region'" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "To create a new Cloud SQL instance:" -ForegroundColor Yellow
    Write-Host "gcloud sql instances create $InstanceName \"
    Write-Host "  --database-version=MYSQL_8_0 \"
    Write-Host "  --tier=db-f1-micro \"
    Write-Host "  --region=$Region"
    Write-Host ""
    exit 1
}

# Get Cloud Run service account
Write-Host "🔐 Configuring Cloud Run service account..." -ForegroundColor Yellow
$projectNumber = gcloud projects describe $ProjectId --format="value(projectNumber)"
$serviceAccount = "$projectNumber-compute@developer.gserviceaccount.com"

Write-Host "Cloud Run Service Account: $serviceAccount"
Write-Host ""

# Add Cloud SQL Client role to service account
Write-Host "Granting Cloud SQL Client role to Cloud Run service account..." -ForegroundColor Yellow
gcloud projects add-iam-policy-binding $ProjectId `
    --member="serviceAccount:$serviceAccount" `
    --role="roles/cloudsql.client" `
    --condition=None

Write-Host "✅ Cloud Run service account now has Cloud SQL Client permissions" -ForegroundColor Green
Write-Host ""

# Instructions for database credentials
Write-Host "📝 Next Steps:" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Set database credentials as environment variables or secrets:" -ForegroundColor Yellow
Write-Host ""
Write-Host "   Option A: Environment Variables (for testing):" -ForegroundColor Yellow
Write-Host "   # Database name 'base' is already set by default"
Write-Host "   gcloud run services update $ServiceName \"
Write-Host "     --region $Region \"
Write-Host "     --update-env-vars DB_DATABASE=base,DB_USERNAME=your_username,DB_PASSWORD=your_password \"
Write-Host "     --project $ProjectId"
Write-Host ""
Write-Host "   Option B: Secret Manager (recommended for production):" -ForegroundColor Yellow
Write-Host "   # Create secrets (database name is 'base')"
Write-Host "   echo -n 'base' | gcloud secrets create db-database --data-file=-"
Write-Host "   echo -n 'your_username' | gcloud secrets create db-username --data-file=-"
Write-Host "   echo -n 'your_password' | gcloud secrets create db-password --data-file=-"
Write-Host ""
Write-Host "   # Grant access"
Write-Host "   gcloud secrets add-iam-policy-binding db-database \"
Write-Host "     --member=serviceAccount:$serviceAccount \"
Write-Host "     --role=roles/secretmanager.secretAccessor"
Write-Host "   # Repeat for db-username and db-password"
Write-Host ""
Write-Host "   # Update service"
Write-Host "   gcloud run services update $ServiceName \"
Write-Host "     --region $Region \"
Write-Host "     --update-secrets DB_DATABASE=db-database:latest,DB_USERNAME=db-username:latest,DB_PASSWORD=db-password:latest \"
Write-Host "     --project $ProjectId"
Write-Host ""
Write-Host "2. The Cloud Run service should already have Cloud SQL connection configured." -ForegroundColor Yellow
Write-Host "   DB_HOST should be set to: /cloudsql/$ConnectionName"
Write-Host "   DB_DATABASE should be set to: base"
Write-Host ""
Write-Host "3. Verify the connection:" -ForegroundColor Yellow
Write-Host "   gcloud run services describe $ServiceName --region $Region --format='value(spec.template.spec.containers[0].env)' --project $ProjectId"
Write-Host ""

