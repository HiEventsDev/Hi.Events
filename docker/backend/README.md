# Hi.Events Backend - Production Deployment

Production-ready Docker Compose setup for Hi.Events backend with PostgreSQL, Redis, automated backups, and Cloudflare Tunnel integration.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [First Time Setup (Beginner Friendly)](#first-time-setup-beginner-friendly)
   - [Generate Secure Keys](#step-1-generate-secure-keys)
   - [Configure Database & Redis](#step-2-configure-database--redis-credentials)
   - [Understanding Config Files](#step-3-understanding-the-configuration-files)
3. [Architecture](#architecture)
4. [Prerequisites](#prerequisites)
5. [Configuration](#configuration)
6. [Deployment](#deployment)
7. [Services Overview](#services-overview)
8. [Backup & Restore](#backup--restore)
9. [Cloudflare Tunnel](#cloudflare-tunnel)
10. [Common Commands](#common-commands)
11. [Multi-Instance Deployment](#multi-instance-deployment)
12. [Troubleshooting](#troubleshooting)
13. [Environment Variables Reference](#environment-variables-reference)

---

## Quick Start

Get the backend running in 5 minutes:

```bash
cd docker/backend
chmod +x deploy.sh
./deploy.sh
```

**First time?** The script will detect if `backend/.env` is missing and help you create it. Just enter your domain when prompted.

**Then select option 1** (First time deploy)

This will:
- ✅ Generate secure APP_KEY and JWT_SECRET
- ✅ Generate secure database and Redis passwords
- ✅ Build the backend image using your `backend/Dockerfile`
- ✅ Start PostgreSQL 17 with your configured credentials
- ✅ Start Redis with your configured password
- ✅ Connect them all on an internal network
- ✅ Bind backend to `localhost:8080` only
- ✅ Run database migrations
- ✅ Optionally configure auto-start on boot

Verify it's working:
```bash
# Check containers
podman ps

# Test API
curl http://localhost:8080/api/health

# View logs
podman logs -f hievents-backend
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Your VPS                            │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐  │
│  │   Backend       │  │   PostgreSQL    │  │    Redis    │  │
│  │   Port: 8080    │  │   Port: 5432    │  │   Port:6379 │  │
│  │   (localhost)   │  │   (internal)    │  │  (internal) │  │
│  └────────┬────────┘  └─────────────────┘  └─────────────┘  │
│           │                                                 │
│           │  localhost:8080                                 │
│           ▼                                                 │
│  ┌─────────────────────────────────────┐                   │
│  │     Cloudflared Tunnel              │                   │
│  │     (outbound connection)           │                   │
│  └────────┬────────────────────────────┘                   │
└───────────┼─────────────────────────────────────────────────┘
            │
            ▼ HTTPS
   ┌──────────────────┐
   │  Cloudflare Edge │
   │  api.yourdomain  │
   └──────────────────┘
```

**Security Features:**
- Backend binds only to `127.0.0.1:8080` (not accessible externally)
- Database and Redis are internal only (no exposed ports)
- Automatic container restart on failure
- Health checks ensure services are healthy

---

## Prerequisites

- **Podman** or **Docker** installed
- `podman-compose` or `docker compose` plugin
- **Backend code** in `../../backend/` directory (with Dockerfile)
- (Optional) `cloudflared` for tunnel setup

> 💡 **Note:** The `backend/.env` file will be created automatically when you first run `./deploy.sh` if it doesn't exist.

---

## First Time Setup (Beginner Friendly)

This guide will walk you through setting up your backend for the first time. **Only ONE file needs to be configured: `backend/.env`**

> 💡 **Good news:** The `docker-compose.prod.yml` automatically reads all database and Redis credentials from your `backend/.env` file. No need to edit multiple files!

---

### Option 1: Interactive Setup (Easiest - Recommended)

Simply run the deploy script and it will guide you through creating your configuration:

```bash
cd docker/backend
chmod +x deploy.sh
./deploy.sh
```

If no `.env` file exists, you'll see:
```
⚠ Configuration file not found: backend/.env

This is your first time setup. Let's create the configuration file.

Enter your frontend domain (e.g., tickets.hievents.com): 
```

Enter your domain and the script will:
- ✅ Generate secure APP_KEY and JWT_SECRET
- ✅ Generate secure database password
- ✅ Generate secure Redis password
- ✅ Create `backend/.env` with all settings

Then run `./deploy.sh` again and select option **1** to deploy!

---

### Option 2: Manual Setup (If you prefer full control)

#### Step 1: Create backend/.env

Create and edit `backend/.env`:

```bash
cd backend
cat > .env << 'ENVEOF'
# Application Settings
APP_NAME=hievents
APP_ENV=production
APP_KEY=base64:REPLACE_WITH_GENERATED_KEY
APP_DEBUG=false
APP_URL=http://127.0.0.1:8080
APP_FRONTEND_URL=https://tickets.yourdomain.com

# Database Settings (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hievents
DB_USERNAME=hievents
DB_PASSWORD=REPLACE_WITH_SECURE_PASSWORD

# Redis Settings
REDIS_HOST=redis
REDIS_PASSWORD=REPLACE_WITH_SECURE_PASSWORD
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# Security
JWT_SECRET=REPLACE_WITH_GENERATED_SECRET
JWT_ALGO=HS256

# Logging
LOG_CHANNEL=stderr
LOG_LEVEL=info
ENVEOF
```

#### Step 2: Generate Secure Keys

Replace the placeholder values in your `.env` file:

```bash
# Generate APP_KEY (base64 encoded)
openssl rand -base64 32
# Copy output and replace: APP_KEY=base64:YOUR_GENERATED_KEY

# Generate JWT_SECRET
openssl rand -base64 32
# Copy output and replace: JWT_SECRET=YOUR_GENERATED_SECRET

# Generate database password
openssl rand -base64 24 | tr -d '/+='
# Copy output and replace: DB_PASSWORD=YOUR_PASSWORD

# Generate Redis password (can be same or different from DB)
openssl rand -base64 24 | tr -d '/+='
# Copy output and replace: REDIS_PASSWORD=YOUR_PASSWORD
```

Or use sed to replace them automatically:

```bash
cd backend

# Generate values
APP_KEY=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 32)
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=')
REDIS_PASS=$(openssl rand -base64 24 | tr -d '/+=')

# Update .env file
sed -i "s|APP_KEY=base64:REPLACE_WITH_GENERATED_KEY|APP_KEY=base64:${APP_KEY}|" .env
sed -i "s|JWT_SECRET=REPLACE_WITH_GENERATED_SECRET|JWT_SECRET=${JWT_SECRET}|" .env
sed -i "s|DB_PASSWORD=REPLACE_WITH_SECURE_PASSWORD|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|REDIS_PASSWORD=REPLACE_WITH_SECURE_PASSWORD|REDIS_PASSWORD=${REDIS_PASS}|" .env

echo "✓ Configuration complete!"
```

#### Step 3: Deploy

```bash
cd docker/backend
./deploy.sh
# Select option 1
```

---

### Understanding Your .env File

The `backend/.env` file is the **only** file you need to configure. Here's what each section does:

```bash
# Application Settings
APP_NAME=hievents                    # Your app name
APP_ENV=production                    # Environment (keep as production)
APP_KEY=base64:xxx                    # Auto-generated encryption key
APP_DEBUG=false                       # Disable debug mode in production
APP_URL=http://127.0.0.1:8080         # Backend URL (localhost only)
APP_FRONTEND_URL=https://...          # Your frontend domain

# Database Settings (PostgreSQL)
DB_CONNECTION=pgsql                   # Database type (keep as pgsql)
DB_HOST=postgres                      # Container name (don't change)
DB_PORT=5432                          # PostgreSQL port
DB_DATABASE=hievents                 # Database name
DB_USERNAME=hievents                 # Database user
DB_PASSWORD=xxx                       # Auto-generated secure password

# Redis Settings
REDIS_HOST=redis                      # Container name (don't change)
REDIS_PASSWORD=xxx                    # Auto-generated secure password
REDIS_PORT=6379                       # Redis port
QUEUE_CONNECTION=redis                # Use Redis for queues

# Security
JWT_SECRET=xxx                        # Auto-generated JWT secret
JWT_ALGO=HS256                        # JWT algorithm (keep as HS256)

# Logging
LOG_CHANNEL=stderr                    # Log to stderr (best for containers)
LOG_LEVEL=info                        # Log level
```

> 🔒 **Important:** Never share your `.env` file or commit it to Git. It contains sensitive passwords and keys.

---

## Configuration

### Required Variables in backend/.env

Your `backend/.env` file is the **only** configuration file you need to edit. All services (backend, PostgreSQL, Redis) read their credentials from this single file.

See [First Time Setup](#first-time-setup-beginner-friendly) above for detailed instructions.

Quick reference for required variables:

```bash
# Required - Security keys
APP_KEY=base64:your_base64_key_here
JWT_SECRET=your_jwt_secret_here

# Required - Your frontend domain
APP_FRONTEND_URL=https://tickets.yourdomain.com

# Required - Database credentials
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hievents
DB_USERNAME=hievents
DB_PASSWORD=your_secure_password

# Required - Redis credentials
REDIS_HOST=redis
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# Required - Logging
LOG_CHANNEL=stderr
LOG_LEVEL=info
```

> 💡 **How it works:** The `docker-compose.prod.yml` automatically reads `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `REDIS_PASSWORD` from your `backend/.env` file. No need to edit `docker-compose.prod.yml`!

---

## Deployment

### Method 1: Interactive Deploy Script (Recommended)

```bash
cd docker/backend
chmod +x deploy.sh
./deploy.sh
```

**Menu Options:**

| Option | Description |
|--------|-------------|
| 0 | **Generate .env file** - Create/regenerate configuration (first time setup) |
| 1 | **First time deploy** - Build, start containers, run migrations |
| 2 | Start existing containers |
| 3 | Stop containers |
| 4 | Restart containers |
| 5 | View logs (follow mode) |
| 6 | Update - Pull latest code, rebuild, backup (optional), run migrations |
| 7 | Database backup - Create manual backup |
| 8 | Database restore - Restore from backup (with confirmation) |
| 9 | Shell into backend container |
| 10 | Run migrations manually |
| 11 | Configure auto-start on boot (systemd) |

### Method 2: Manual Deployment

```bash
cd docker/backend

# With Podman
podman-compose -f docker-compose.prod.yml up --build -d

# With Docker
docker compose -f docker-compose.prod.yml up --build -d

# Run migrations
podman exec hievents-backend php artisan migrate --force
```

### Post-Deployment

After deployment, the backend is available at `http://localhost:8080` (localhost only).

To expose it to the internet, set up [Cloudflare Tunnel](#cloudflare-tunnel).

---

## Services Overview

| Service | Container Name | Port | Description |
|---------|---------------|------|-------------|
| Backend | hievents-backend | 127.0.0.1:8080 | PHP/Laravel API |
| PostgreSQL | hievents-postgres | internal | Database |
| Redis | hievents-redis | internal | Cache & Queues |
| Backup | hievents-pgbackup | - | Daily backups (retains 7 days) |

### Data Persistence

Data is stored in Docker/Podman volumes:
- `hievents-pgdata` - PostgreSQL data
- `hievents-redisdata` - Redis data
- `hievents-storage` - Laravel storage
- `hievents-cache` - Laravel cache
- `./backups/` - Database backups (on host)

---

## Backup & Restore

### Automatic Backups

Backups run daily at 2 AM and are saved to `./backups/`. Last 7 backups are kept.

### Manual Backup

Using deploy script:
```bash
./deploy.sh
# Select option 7
```

Or manually:
```bash
podman exec hievents-postgres pg_dump -U hievents -d hievents > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore from Backup

Using deploy script (with confirmation):
```bash
./deploy.sh
# Select option 8 and choose backup
```

Or manually (⚠️ **Warning:** This destroys current data):
```bash
# Stop the backend first
podman stop hievents-backend

# Restore database
podman exec -i hievents-postgres psql -U hievents -d hievents < backup_file.sql

# Restart backend
podman start hievents-backend
```

---

## Cloudflare Tunnel

Since the backend binds to `localhost:8080`, use `cloudflared` to expose it securely:

### Installation

```bash
# Download and install
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64
sudo mv cloudflared-linux-amd64 /usr/local/bin/cloudflared
sudo chmod +x /usr/local/bin/cloudflared
```

### Setup

```bash
# Authenticate
cloudflared tunnel login

# Create tunnel
cloudflared tunnel create hievents-backend

# Route DNS (adjust domain)
cloudflared tunnel route dns hievents-backend api.yourdomain.com
```

### Configuration

Create `~/.cloudflared/config.yml`:

```yaml
tunnel: <YOUR_TUNNEL_ID>
credentials-file: ~/.cloudflared/<YOUR_TUNNEL_ID>.json

ingress:
  - hostname: api.yourdomain.com
    service: http://localhost:8080
  - service: http_status:404
```

### Run Tunnel

```bash
# Run manually
cloudflared tunnel run hievents-backend

# Or install as service
cloudflared service install
systemctl start cloudflared
```

### Update Frontend

Once the tunnel is running, update your frontend `.env`:

```bash
VITE_API_URL_CLIENT=https://api.yourdomain.com
VITE_API_URL_SERVER=https://api.yourdomain.com
```

Redeploy the frontend to apply changes.

---

## Common Commands

### Container Management

```bash
# View all containers
podman ps

# View logs (all services)
podman-compose -f docker-compose.prod.yml logs -f

# View specific service logs
podman logs -f hievents-backend
podman logs -f hievents-postgres
```

### Artisan Commands

```bash
# Run migrations
podman exec hievents-backend php artisan migrate

# Clear cache
podman exec hievents-backend php artisan cache:clear
podman exec hievents-backend php artisan config:clear

# Queue worker
podman exec hievents-backend php artisan queue:work

# Tinker (interactive shell)
podman exec -it hievents-backend php artisan tinker
```

### Database Access

```bash
# PostgreSQL shell
podman exec -it hievents-postgres psql -U hievents

# Run SQL query
podman exec hievents-postgres psql -U hievents -c "SELECT * FROM users LIMIT 5;"
```

### Container Shell

```bash
# Backend shell
podman exec -it hievents-backend sh

# PostgreSQL shell
podman exec -it hievents-postgres sh
```

### Start/Stop

```bash
# Start
podman-compose -f docker-compose.prod.yml up -d

# Stop
podman-compose -f docker-compose.prod.yml down

# Stop and remove volumes (WARNING: deletes data!)
podman-compose -f docker-compose.prod.yml down -v
```

---

## Multi-Instance Deployment

To deploy multiple instances on the same server:

### Step 1: Create Instance Directory

```bash
mkdir -p instances/client1
cd instances/client1
```

### Step 2: Create docker-compose.yml

Copy and modify the main `docker-compose.prod.yml`:

```bash
# Adjust these for each instance:
# - Service names (e.g., backend → client1-backend)
# - Container names (e.g., hievents-backend → client1-backend)
# - Ports (e.g., 8080 → 8081)
# - Volume names (e.g., hievents-pgdata → client1-pgdata)
```

### Step 3: Create .env File

```bash
cat > .env << EOF
APP_NAME=client1
APP_URL=http://localhost:8081
APP_FRONTEND_URL=https://client1.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=client1-postgres
DB_PORT=5432
DB_DATABASE=client1
DB_USERNAME=client1
DB_PASSWORD=$(openssl rand -base64 32)

REDIS_HOST=client1-redis
REDIS_PASSWORD=$(openssl rand -base64 32)
REDIS_PORT=6379

QUEUE_CONNECTION=redis
LOG_CHANNEL=stderr
EOF
```

### Step 4: Deploy

```bash
podman-compose -f docker-compose.yml up --build -d
podman exec client1-backend php artisan migrate --force
```

### Automation Script

```bash
#!/bin/bash
# deploy-instance.sh

INSTANCE_NAME=$1
BACKEND_PORT=$2

if [ -z "$INSTANCE_NAME" ] || [ -z "$BACKEND_PORT" ]; then
    echo "Usage: $0 <instance_name> <backend_port>"
    echo "Example: $0 client1 8081"
    exit 1
fi

# Create directory
mkdir -p "instances/$INSTANCE_NAME"
cd "instances/$INSTANCE_NAME"

# Generate .env
DB_PASS=$(openssl rand -base64 32)
REDIS_PASS=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 32)
APP_KEY=$(openssl rand -base64 32)

cat > .env << EOF
APP_NAME=$INSTANCE_NAME
APP_ENV=production
APP_KEY=base64:$APP_KEY
APP_DEBUG=false
APP_URL=http://localhost:$BACKEND_PORT
APP_FRONTEND_URL=https://$INSTANCE_NAME.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=${INSTANCE_NAME}-postgres
DB_PORT=5432
DB_DATABASE=$INSTANCE_NAME
DB_USERNAME=$INSTANCE_NAME
DB_PASSWORD=$DB_PASS

REDIS_HOST=${INSTANCE_NAME}-redis
REDIS_PASSWORD=$REDIS_PASS
REDIS_PORT=6379

JWT_SECRET=$JWT_SECRET
QUEUE_CONNECTION=redis
LOG_CHANNEL=stderr
EOF

echo "Instance $INSTANCE_NAME created!"
echo "Backend port: $BACKEND_PORT"
echo "Next steps:"
echo "1. Create docker-compose.yml (copy and modify from main)"
echo "2. Deploy: cd instances/$INSTANCE_NAME && podman-compose up -d"
echo "3. Run migrations: podman exec ${INSTANCE_NAME}-backend php artisan migrate"
```

Usage:
```bash
chmod +x deploy-instance.sh
./deploy-instance.sh client1 8081
./deploy-instance.sh client2 8082
```

---

## Troubleshooting

### Containers Won't Start

```bash
# Check logs
podman logs hievents-backend
podman logs hievents-postgres

# Check if ports are in use
ss -tlnp | grep 8080

# Check container status
podman ps -a
```

### Database Connection Issues

```bash
# Check if postgres is healthy
podman ps
podman healthcheck run hievents-postgres

# Test connection from backend
podman exec -it hievents-backend php artisan tinker
>>> DB::connection()->getPdo();
```

### Migration Failures

```bash
# Check migration status
podman exec hievents-backend php artisan migrate:status

# Run with verbose output
podman exec hievents-backend php artisan migrate --force -v
```

### Reset Everything (WARNING: Deletes All Data)

```bash
# Stop and remove containers + volumes
podman-compose -f docker-compose.prod.yml down -v

# Remove backups
rm -rf backups/*

# Start fresh
./deploy.sh
# Select option 1
```

### Permission Issues

```bash
# Fix storage permissions
podman exec hievents-backend chown -R www-data:www-data storage/
podman exec hievents-backend chown -R www-data:www-data bootstrap/cache/
```

### Out of Disk Space

```bash
# Check volume sizes
podman system df -v

# Clean up unused images
podman image prune -a

# Clean up stopped containers
podman container prune
```

---

## File Structure

```
docker/backend/
├── docker-compose.prod.yml    # Production compose configuration
├── deploy.sh                  # Interactive deployment script
├── backups/                   # Database backups (auto-created)
├── instances/                 # Multi-instance deployments (optional)
│   ├── client1/
│   │   ├── docker-compose.yml
│   │   └── .env
│   └── client2/
│       ├── docker-compose.yml
│       └── .env
└── README.md                  # This file
```

---

## Environment Variables Reference

All `APP_*` environment variables used in the backend:

### Core Application Settings

#### APP_NAME
Application name displayed in emails and UI.  
**Default:** `Hi.Events`  
**Example:** `APP_NAME="hievents Tickets"`

#### APP_ENV
Application environment.  
**Options:** `local`, `development`, `production`, `testing`  
**Example:** `APP_ENV=production`

#### APP_KEY
Encryption key for sessions and cookies. **Required.**  
**Format:** `base64:` prefix + base64-encoded 32-byte string  
**Example:** `APP_KEY=base64:tM2Vl+qUu+9MnxN2qukcHF0JEbSBvZ3KjjU4+sSh2ow=`

#### APP_DEBUG
Enable detailed error messages. **Never true in production.**  
**Example:** `APP_DEBUG=false`

#### APP_URL
Backend API base URL.  
**Example:** `APP_URL=http://127.0.0.1:8080`

#### APP_FRONTEND_URL
Frontend URL for CORS and redirects.  
**Example:** `APP_FRONTEND_URL=https://tickets.hievents.com`

#### APP_CDN_URL
CDN URL for static assets.  
**Example:** `APP_CDN_URL=https://cdn.hievents.com`

---

### SaaS Mode Settings

#### APP_SAAS_MODE_ENABLED
Enable multi-tenant SaaS mode.  
**Options:** `true`, `false`  
**Example:** `APP_SAAS_MODE_ENABLED=false`

#### APP_SAAS_STRIPE_APPLICATION_FEE_PERCENT
Platform fee percentage (SaaS only).  
**Default:** `1.5`  
**Example:** `APP_SAAS_STRIPE_APPLICATION_FEE_PERCENT=1.5`

#### APP_PLATFORM_SUPPORT_EMAIL
Support email for platform issues.  
**Example:** `APP_PLATFORM_SUPPORT_EMAIL=support@hievents.com`

---

### Access Control

#### APP_DISABLE_REGISTRATION
Disable new user registration.  
**Example:** `APP_DISABLE_REGISTRATION=false`

#### APP_ENFORCE_EMAIL_CONFIRMATION_DURING_REGISTRATION
Require email confirmation.  
**Example:** `APP_ENFORCE_EMAIL_CONFIRMATION_DURING_REGISTRATION=false`

---

### Stripe Integration

#### APP_STRIPE_CONNECT_ACCOUNT_TYPE
Stripe Connect account type.  
**Options:** `express`, `standard`, `custom`  
**Example:** `APP_STRIPE_CONNECT_ACCOUNT_TYPE=express`

---

### Performance & Caching

#### APP_HOMEPAGE_VIEWS_UPDATE_BATCH_SIZE
Batch size for view counting. Increase for high traffic.  
**Default:** `8`  
**Example:** `APP_HOMEPAGE_VIEWS_UPDATE_BATCH_SIZE=8`

#### APP_API_RATE_LIMIT_PER_MINUTE
API rate limit per minute.  
**Default:** `180`  
**Example:** `APP_API_RATE_LIMIT_PER_MINUTE=180`

---

### Email Customization

#### APP_EMAIL_LOGO_URL
Logo URL for emails.  
**Example:** `APP_EMAIL_LOGO_URL=https://cdn.hievents.com/logo.png`

#### APP_EMAIL_FOOTER_TEXT
Custom footer text for emails.  
**Example:** `APP_EMAIL_FOOTER_TEXT="© 2025 hievents"`

---

### Complete Example

```bash
# Core
APP_NAME="hievents Tickets"
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=http://127.0.0.1:8080
APP_FRONTEND_URL=https://tickets.hievents.com

# SaaS
APP_SAAS_MODE_ENABLED=false
APP_PLATFORM_SUPPORT_EMAIL=support@hievents.com

# Stripe
APP_STRIPE_CONNECT_ACCOUNT_TYPE=express

# Access
APP_DISABLE_REGISTRATION=false
```

---

**License:** AGPL-3.0 | **Project:** [Hi.Events](https://github.com/HiEventsDev/hi.events)
