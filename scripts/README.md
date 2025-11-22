# Deployment Scripts

This directory contains scripts for deploying and managing the application on Google Cloud Run.

## Scripts

### Deployment Scripts

- **`deploy-cloudrun.ps1`** / **`deploy-cloudrun.sh`**
  - Deploys the application to Google Cloud Run
  - Builds Docker image and deploys to Cloud Run service
  - Automatically configures Cloud SQL connection
  - Usage:
    ```powershell
    .\scripts\deploy-cloudrun.ps1 -ProjectId "your-project-id"
    ```
    ```bash
    ./scripts/deploy-cloudrun.sh your-project-id asia-southeast1
    ```

### Environment Management

- **`apply-env-cloudrun.ps1`** / **`apply-env-cloudrun.sh`**
  - Applies environment variables from `.env.cloudrun` file to Cloud Run service
  - Preserves Cloud SQL socket paths
  - Usage:
    ```powershell
    .\scripts\apply-env-cloudrun.ps1 -ProjectId "your-project-id" -Region "asia-southeast1"
    ```
    ```bash
    ./scripts/apply-env-cloudrun.sh your-project-id asia-southeast1 webhook
    ```

### Cloud SQL Setup

- **`setup-cloudsql.ps1`** / **`setup-cloudsql.sh`**
  - Configures Cloud SQL connection and permissions
  - Verifies Cloud SQL instance exists
  - Sets up IAM permissions for Cloud Run service account
  - Usage:
    ```powershell
    .\scripts\setup-cloudsql.ps1 -ProjectId "your-project-id" -Region "asia-southeast1"
    ```
    ```bash
    ./scripts/setup-cloudsql.sh your-project-id asia-southeast1 base-image
    ```

## Quick Start

1. **Deploy the application:**
   ```powershell
   .\scripts\deploy-cloudrun.ps1 -ProjectId "your-project-id"
   ```

2. **Apply environment variables:**
   ```powershell
   .\scripts\apply-env-cloudrun.ps1 -ProjectId "your-project-id"
   ```

3. **Configure Cloud SQL (if needed):**
   ```powershell
   .\scripts\setup-cloudsql.ps1 -ProjectId "your-project-id"
   ```

## Requirements

- Google Cloud SDK (gcloud) installed and configured
- Appropriate permissions for Cloud Run, Cloud Build, and Cloud SQL
- `.env.cloudrun` file in the project root (for apply-env-cloudrun scripts)

## Notes

- All scripts default to `asia-southeast1` region
- Cloud SQL instance defaults to `base-image`
- Service name defaults to `webhook`
- Database name defaults to `base`

