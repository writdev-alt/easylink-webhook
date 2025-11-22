# Google Cloud Run Deployment Script (PowerShell)
# Usage: .\scripts\deploy-cloudrun.ps1 [-ProjectId] <project-id> [-Region] <region> [-CloudSqlInstance] <instance-name>

param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$ProjectId,
    
    [Parameter(Mandatory=$false, Position=1)]
    [string]$Region = "asia-southeast1",
    
    [Parameter(Mandatory=$false, Position=2)]
    [string]$CloudSqlInstance = "base-image",
    
    [Parameter(Mandatory=$false)]
    [switch]$LoadEnvFile,
    
    [Parameter(Mandatory=$false)]
    [string]$EnvFile = ".env.cloudrun"
)

# Set error action preference
$ErrorActionPreference = "Stop"

# Configuration
$ServiceName = "webhook"
$ImageName = "gcr.io/$ProjectId/$ServiceName"
$ImageNameWithTag = "$ImageName:latest"
$CloudSqlConnectionName = "$ProjectId`:$Region`:$CloudSqlInstance"

# Ensure ImageName is clean (no trailing slashes or colons)
$ImageName = $ImageName.TrimEnd(":", "/")

# Function to load environment variables from .env file
function Load-EnvFile {
    param([string]$FilePath)
    
    if (-not (Test-Path $FilePath)) {
        Write-Host "Warning: Environment file '$FilePath' not found. Using default variables only." -ForegroundColor Yellow
        return @()
    }
    
    $envVars = @()
    $content = Get-Content $FilePath
    
    foreach ($line in $content) {
        # Skip empty lines and comments
        if ($line -match '^\s*#|^\s*$') {
            continue
        }
        
        # Parse KEY=VALUE format
        if ($line -match '^([^=]+)=(.*)$') {
            $key = $matches[1].Trim()
            $value = $matches[2].Trim()
            
            # Remove quotes if present
            if ($value -match '^"(.*)"$' -or $value -match "^'(.*)'$") {
                $value = $matches[1]
            }
            
            # Skip empty values
            if ([string]::IsNullOrWhiteSpace($value)) {
                continue
            }
            
            $envVars += "$key=$value"
        }
    }
    
    return $envVars
}

Write-Host "🚀 Deploying to Google Cloud Run" -ForegroundColor Cyan
Write-Host "Project ID: $ProjectId"
Write-Host "Region: $Region"
Write-Host "Service: $ServiceName"
Write-Host "Cloud SQL Instance: $CloudSqlInstance"
Write-Host "Cloud SQL Connection: $CloudSqlConnectionName"
Write-Host "Database Name: base"
Write-Host ""

# Set the project
Write-Host "Setting GCP project..." -ForegroundColor Yellow
gcloud config set project $ProjectId
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to set GCP project" -ForegroundColor Red
    exit 1
}

# Enable required APIs
Write-Host "Enabling required APIs..." -ForegroundColor Yellow
$apis = @(
    "cloudbuild.googleapis.com",
    "run.googleapis.com",
    "containerregistry.googleapis.com",
    "sqladmin.googleapis.com"
)

foreach ($api in $apis) {
    Write-Host "  Enabling $api..."
    gcloud services enable $api --project $ProjectId 2>&1 | Out-Null
}

# Build the Docker image
Write-Host "Building Docker image..." -ForegroundColor Yellow
Write-Host "Image name: $ImageName" -ForegroundColor Gray

# Build with substitutions
# Cloud Build substitutions format: KEY=VALUE
# Pass the full image name with :latest tag in the substitution
Write-Host "Substituting _IMAGE_NAME with: $ImageNameWithTag" -ForegroundColor Gray

# Use --substitutions with KEY=VALUE format (pass image name with :latest tag)
gcloud builds submit --config cloudbuild-build.yaml --substitutions "_IMAGE_NAME=$ImageNameWithTag" --project $ProjectId
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to build Docker image" -ForegroundColor Red
    exit 1
}

# Deploy to Cloud Run with Cloud SQL connection
Write-Host "Deploying to Cloud Run with Cloud SQL connection..." -ForegroundColor Yellow

# Build environment variables
$baseEnvVars = @(
    "APP_ENV=production",
    "DB_HOST=/cloudsql/$CloudSqlConnectionName",
    "DB_DATABASE=base",
    "DB_HOST_SITE=/cloudsql/$CloudSqlConnectionName"
)

# Load additional env vars from file if requested
if ($LoadEnvFile) {
    Write-Host "Loading environment variables from $EnvFile..." -ForegroundColor Yellow
    $fileEnvVars = Load-EnvFile -FilePath $EnvFile
    
    # Override DB_HOST and DB_HOST_SITE with Cloud SQL socket path
    $fileEnvVars = $fileEnvVars | ForEach-Object {
        if ($_ -match '^DB_HOST=' -or $_ -match '^DB_HOST_SITE=') {
            # Skip, we'll use the Cloud SQL socket path
            return $null
        }
        return $_
    } | Where-Object { $null -ne $_ }
    
    $baseEnvVars += $fileEnvVars
}

$envVarsString = $baseEnvVars -join ","

gcloud run deploy $ServiceName `
    --image $ImageNameWithTag `
    --platform managed `
    --region $Region `
    --allow-unauthenticated `
    --port 8080 `
    --memory 512Mi `
    --cpu 1 `
    --min-instances 0 `
    --max-instances 10 `
    --timeout 300 `
    --concurrency 80 `
    --add-cloudsql-instances $CloudSqlConnectionName `
    --set-env-vars $envVarsString `
    --project $ProjectId

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to deploy to Cloud Run" -ForegroundColor Red
    exit 1
}

# Get the service URL
Write-Host ""
Write-Host "✅ Deployment complete!" -ForegroundColor Green
Write-Host ""

$serviceUrl = gcloud run services describe $ServiceName --region $Region --format 'value(status.url)' --project $ProjectId
Write-Host "Service URL: $serviceUrl" -ForegroundColor Cyan
Write-Host ""

Write-Host "To update environment variables:" -ForegroundColor Yellow
Write-Host "gcloud run services update $ServiceName --region $Region --update-env-vars KEY=VALUE --project $ProjectId"
Write-Host ""

Write-Host "To configure Cloud SQL credentials, run:" -ForegroundColor Yellow
Write-Host ".\scripts\setup-cloudsql.ps1 -ProjectId $ProjectId -Region $Region -InstanceName $CloudSqlInstance"
Write-Host ""

if (-not $LoadEnvFile) {
    Write-Host "To apply environment variables from .env.cloudrun, run:" -ForegroundColor Yellow
    Write-Host ".\scripts\apply-env-cloudrun.ps1 -ProjectId $ProjectId -Region $Region" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Or deploy with environment variables loaded:" -ForegroundColor Yellow
    Write-Host ".\scripts\deploy-cloudrun.ps1 -ProjectId $ProjectId -LoadEnvFile" -ForegroundColor Cyan
    Write-Host ""
}

