# Apply Environment Variables to Cloud Run from .env.cloudrun file
# Usage: .\scripts\apply-env-cloudrun.ps1 [-ProjectId] <project-id> [-Region] <region> [-ServiceName] <service-name>

param(
    [Parameter(Mandatory=$true, Position=0)]
    [string]$ProjectId,
    
    [Parameter(Mandatory=$false, Position=1)]
    [string]$Region = "asia-southeast1",
    
    [Parameter(Mandatory=$false, Position=2)]
    [string]$ServiceName = "webhook",
    
    [Parameter(Mandatory=$false, Position=3)]
    [string]$CloudSqlInstance = "base-image",
    
    [Parameter(Mandatory=$false)]
    [string]$EnvFile = ".env.cloudrun"
)

$ErrorActionPreference = "Stop"

# Check if .env.cloudrun file exists
if (-not (Test-Path $EnvFile)) {
    Write-Host "Error: Environment file '$EnvFile' not found!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Applying environment variables from $EnvFile to Cloud Run service" -ForegroundColor Cyan
Write-Host "Project ID: $ProjectId"
Write-Host "Region: $Region"
Write-Host "Service: $ServiceName"
Write-Host ""

# Check if service exists
Write-Host "Checking if Cloud Run service exists..." -ForegroundColor Yellow
gcloud run services describe $ServiceName --region $Region --project $ProjectId 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Cloud Run service '$ServiceName' not found in region '$Region'!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please deploy the service first using:" -ForegroundColor Yellow
    Write-Host ".\scripts\deploy-cloudrun.ps1 -ProjectId $ProjectId -Region $Region" -ForegroundColor Cyan
    Write-Host ""
    exit 1
}

Write-Host "✅ Service found, proceeding with environment variable update..." -ForegroundColor Green
Write-Host ""

# Get current service configuration to preserve Cloud SQL settings
Write-Host "Reading current service configuration..." -ForegroundColor Yellow
$currentService = gcloud run services describe $ServiceName --region $Region --project $ProjectId --format="json" | ConvertFrom-Json
$currentEnvVars = @{}

# Get current environment variables
if ($currentService.spec.template.spec.containers[0].env) {
    foreach ($envVar in $currentService.spec.template.spec.containers[0].env) {
        if ($envVar.name) {
            $currentEnvVars[$envVar.name] = $envVar.value
        }
    }
}

# Read the .env file and convert to key=value pairs
$envVars = @()
$content = Get-Content $EnvFile

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
        
        # Skip if value is empty
        if ([string]::IsNullOrWhiteSpace($value)) {
            continue
        }
        
        # Preserve Cloud SQL socket path if it exists in current config
        if ($key -eq "DB_HOST" -or $key -eq "DB_HOST_SITE") {
            if ($currentEnvVars.ContainsKey($key) -and $currentEnvVars[$key] -match "^/cloudsql/") {
                Write-Host "Preserving Cloud SQL socket path for $key" -ForegroundColor Cyan
                $value = $currentEnvVars[$key]
            } elseif ($value -notmatch "^/cloudsql/") {
                # Convert IP to Cloud SQL socket if needed
                $cloudSqlConnection = "$ProjectId`:$Region`:$CloudSqlInstance"
                $value = "/cloudsql/$cloudSqlConnection"
                Write-Host "Converting $key to Cloud SQL socket path: $value" -ForegroundColor Cyan
            }
        }
        
        # Replace variable references
        if ($value -match '\$\{([^}]+)\}') {
            $varName = $matches[1]
            $varValue = [Environment]::GetEnvironmentVariable($varName)
            if ($varValue) {
                $value = $value -replace '\$\{' + [regex]::Escape($varName) + '\}', $varValue
            }
        }
        
        $envVars += "$key=$value"
    }
}

# Join all environment variables
$envVarsString = $envVars -join ","

Write-Host "Updating Cloud Run service with environment variables..." -ForegroundColor Yellow
gcloud run services update $ServiceName `
    --region $Region `
    --update-env-vars $envVarsString `
    --project $ProjectId

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to update environment variables" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "✅ Environment variables applied successfully!" -ForegroundColor Green
Write-Host ""

