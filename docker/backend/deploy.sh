#!/bin/bash
#
# hievents Backend Deployment Script
#
# This script deploys the backend with PostgreSQL and Redis using Podman or Docker.
# It uses your existing backend/.env for configuration.
#
# Usage: ./deploy.sh
#

set -euo pipefail

#==============================================================================
# Configuration & Constants
#==============================================================================

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly BACKEND_DIR="$(dirname "$SCRIPT_DIR")/../backend"
readonly COMPOSE_FILE="$SCRIPT_DIR/docker-compose.prod.yml"
readonly BACKUP_DIR="$SCRIPT_DIR/backups"
readonly MIN_BACKUP_SIZE=1000

#==============================================================================
# Container Name Resolution
#==============================================================================

# Container names are hardcoded in docker-compose.prod.yml
# This ensures reliable operation with both Docker and Podman
readonly BACKEND_CONTAINER="hievents-backend"
readonly POSTGRES_CONTAINER="hievents-postgres"

get_backend_container() {
  echo "$BACKEND_CONTAINER"
}

get_postgres_container() {
  echo "$POSTGRES_CONTAINER"
}

#==============================================================================
# Logging & Output
#==============================================================================

readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m'

log_info() {
  echo -e "${GREEN}✓${NC} $1"
}

log_warn() {
  echo -e "${YELLOW}⚠${NC} $1"
}

log_error() {
  echo -e "${RED}✗${NC} $1" >&2
}

log_step() {
  echo -e "${BLUE}→${NC} $1"
}

#==============================================================================
# Validation Functions
#==============================================================================

validate_not_root() {
  if [[ "$EUID" -eq 0 ]]; then
    log_error "Please do not run as root (for security)"
    exit 1
  fi
}

validate_container_runtime() {
  if command -v podman &>/dev/null; then
    export RUNTIME="podman"
    export COMPOSE_CMD="podman-compose"
  elif command -v docker &>/dev/null; then
    export RUNTIME="docker"
    export COMPOSE_CMD="docker compose"
  else
    log_error "Neither Podman nor Docker found. Please install one of them."
    exit 1
  fi

  # Verify we can connect to the runtime
  if ! $RUNTIME info &>/dev/null; then
    log_error "Cannot connect to $RUNTIME. Check permissions or try with sudo."
    exit 1
  fi
}

validate_env_file() {
  if [[ ! -f "$BACKEND_DIR/.env" ]]; then
    log_error ".env file not found at $BACKEND_DIR/.env"
    echo ""
    echo "Would you like to generate a new .env file?"
    if confirm "Generate .env file"; then
      generate_env_file
      exit 0
    else
      echo "Please create your .env file first. See README.md for details."
      exit 1
    fi
  fi

  # Source and validate required variables
  # shellcheck source=/dev/null
  source "$BACKEND_DIR/.env"

  local required_vars=("APP_KEY" "JWT_SECRET" "DB_DATABASE" "DB_USERNAME" "DB_PASSWORD" "REDIS_PASSWORD" "STRIPE_PUBLIC_KEY" "STRIPE_SECRET_KEY" "MAIL_HOST")
  local missing_vars=()

  for var in "${required_vars[@]}"; do
    if [[ -z "${!var:-}" ]]; then
      missing_vars+=("$var")
    fi
  done

  if [[ ${#missing_vars[@]} -gt 0 ]]; then
    log_error "Missing required environment variables: ${missing_vars[*]}"
    echo ""
    echo "Your backend/.env file is missing required variables."
    echo "Run './deploy.sh' and select option '0' to generate a complete .env file."
    exit 1
  fi

  # Export all variables for docker-compose to use
  export APP_KEY JWT_SECRET DB_DATABASE DB_USERNAME DB_PASSWORD REDIS_PASSWORD
  export APP_NAME APP_ENV APP_DEBUG APP_URL APP_FRONTEND_URL
  export DB_CONNECTION DB_HOST DB_PORT
  export REDIS_HOST REDIS_PORT QUEUE_CONNECTION
  export LOG_CHANNEL LOG_LEVEL
}

validate_menu_choice() {
  local choice="$1"

  if ! [[ "$choice" =~ ^[0-9]+$ ]]; then
    log_error "Invalid input: '$choice' is not a number"
    return 1
  fi

  if [[ "$choice" -lt 1 || "$choice" -gt 11 ]]; then
    log_error "Invalid choice: $choice. Please enter a number between 0 and 11."
    return 1
  fi
}

validate_backup_file() {
  local file="$1"

  if [[ ! -f "$file" ]]; then
    log_error "Backup file not found: $file"
    return 1
  fi

  local size
  size=$(wc -c <"$file")
  if [[ "$size" -lt "$MIN_BACKUP_SIZE" ]]; then
    log_error "Backup file is too small (${size} bytes), may be corrupted"
    return 1
  fi
}

#==============================================================================
# Utility Functions
#==============================================================================

confirm() {
  local message="$1"
  local response

  read -rp "$message (y/N): " response
  [[ "$response" =~ ^[Yy]$ ]]
}

ensure_backup_dir() {
  mkdir -p "$BACKUP_DIR"
}

get_backup_files() {
  ls -1t "$BACKUP_DIR"/hievents_*.sql 2>/dev/null || true
}

#==============================================================================
# Environment Setup
#==============================================================================

generate_env_file() {
  log_step "Generating secure configuration..."

  # Generate secure random values
  local APP_KEY_VALUE JWT_SECRET_VALUE DB_PASS REDIS_PASS
  APP_KEY_VALUE=$(openssl rand -base64 32)
  JWT_SECRET_VALUE=$(openssl rand -base64 32)
  DB_PASS=$(openssl rand -base64 24 | tr -d '/+=')
  REDIS_PASS=$(openssl rand -base64 24 | tr -d '/+=')

  log_info "Generated secure passwords"

  # Ask for domain
  echo ""
  read -rp "Enter your frontend domain (e.g., tickets.hievents.com): " frontend_domain
  if [[ -z "$frontend_domain" ]]; then
    frontend_domain="tickets.yourdomain.com"
    log_warn "Using default domain: $frontend_domain"
  fi

  # Create .env file with all required variables
  cat >"$BACKEND_DIR/.env" <<EOF
#==============================================================================
# Application Settings
#==============================================================================
APP_NAME=hievents
APP_ENV=production
APP_KEY=base64:${APP_KEY_VALUE}
APP_DEBUG=false
APP_LOG_QUERIES=false
APP_URL=http://127.0.0.1:8080
APP_PORT=8080
APP_FRONTEND_URL=https://${frontend_domain}
APP_CDN_URL=https://cdn.${frontend_domain}
APP_SAAS_MODE_ENABLED=false
APP_SAAS_STRIPE_APPLICATION_FEE_PERCENT=1.5
APP_HOMEPAGE_VIEWS_UPDATE_BATCH_SIZE=8
APP_DISABLE_REGISTRATION=true
APP_STRIPE_CONNECT_ACCOUNT_TYPE=express
APP_PLATFORM_SUPPORT_EMAIL=support@${frontend_domain}

#==============================================================================
# Stripe Payment Settings (Required for payments)
# Get your keys from: https://dashboard.stripe.com/apikeys
#==============================================================================
STRIPE_PUBLIC_KEY=pk_test_your_stripe_publishable_key_here
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

#==============================================================================
# CORS Settings
#==============================================================================
CORS_ALLOWED_ORIGINS=*

#==============================================================================
# Database Settings (PostgreSQL)
# These are used by both the app and the Docker containers
#==============================================================================
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hievents
DB_USERNAME=hievents
DB_PASSWORD=${DB_PASS}

#==============================================================================
# Redis Settings
# These are used by both the app and the Docker containers
#==============================================================================
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASS}
REDIS_PORT=6379

#==============================================================================
# Queue & Cache Settings
#==============================================================================
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=log
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120

#==============================================================================
# Filesystem Settings (local for single VPS, S3/R2 for scalable)
#==============================================================================
FILESYSTEM_PUBLIC_DISK=local #or s3-public
FILESYSTEM_PRIVATE_DISK=local #or s3-private

# AWS/S3 Compatible Storage (R2, S3, etc.) - Uncomment if using cloud storage
# AWS_ACCESS_KEY_ID=your_access_key
# AWS_SECRET_ACCESS_KEY=your_secret_key
# AWS_DEFAULT_REGION=auto
# AWS_PUBLIC_BUCKET=your-public-bucket
# AWS_PRIVATE_BUCKET=your-private-bucket
# AWS_ENDPOINT=https://your-endpoint.com
# AWS_USE_PATH_STYLE_ENDPOINT=true

#==============================================================================
# Mail Settings (Required for sending emails)
#==============================================================================
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@${frontend_domain}"
MAIL_FROM_NAME="\${APP_NAME}"

#==============================================================================
# Logging Settings
#==============================================================================
LOG_CHANNEL=stderr
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

#==============================================================================
# Security Settings (JWT for API authentication)
#==============================================================================
JWT_SECRET=${JWT_SECRET_VALUE}
JWT_ALGO=HS256

#==============================================================================
# Optional: SAAS Mode Settings
# Only required if APP_SAAS_MODE_ENABLED=true
#==============================================================================
# OPEN_EXCHANGE_RATES_APP_ID=your_open_exchange_rates_app_id
EOF

  echo ""
  log_info "Configuration saved to: $BACKEND_DIR/.env"
  echo ""
  echo -e "${YELLOW}Important:${NC}"
  echo "  - APP_KEY: Generated (keep this secret!)"
  echo "  - JWT_SECRET: Generated (keep this secret!)"
  echo "  - Database password: ${DB_PASS:0:8}... (auto-configured)"
  echo "  - Redis password: ${REDIS_PASS:0:8}... (auto-configured)"
  echo ""
  echo "Your backend is now configured. Run './deploy.sh' again to deploy."
}

#==============================================================================
# Deployment Operations
#==============================================================================

op_first_deploy() {
  log_step "Building and starting containers..."
  $COMPOSE_CMD -f "$COMPOSE_FILE" up --build -d

  log_step "Waiting for database to be ready..."
  sleep 5

  op_run_migrations

  echo ""
  log_info "Deployment complete!"
  echo ""
  echo -e "Backend: ${YELLOW}http://localhost:8080${NC}"
  echo -e "Logs:    ${YELLOW}$COMPOSE_CMD -f $COMPOSE_FILE logs -f${NC}"
  echo ""

  if confirm "Configure auto-start on boot?"; then
    op_configure_autostart
  fi
}

op_start() {
  log_step "Starting containers..."
  $COMPOSE_CMD -f "$COMPOSE_FILE" start
  log_info "Containers started"
}

op_stop() {
  log_step "Stopping containers..."
  $COMPOSE_CMD -f "$COMPOSE_FILE" stop
  log_info "Containers stopped"
}

op_restart() {
  log_step "Restarting containers..."
  $COMPOSE_CMD -f "$COMPOSE_FILE" restart
  log_info "Containers restarted"
}

op_logs() {
  log_step "Showing logs (Ctrl+C to exit)..."
  $COMPOSE_CMD -f "$COMPOSE_FILE" logs -f
}

op_update() {
  log_step "Updating code..."
  cd "$BACKEND_DIR"

  # Check for local changes that would block git pull
  if ! git diff-index --quiet HEAD --; then
    log_warn "Local changes detected in $BACKEND_DIR"
    if ! confirm "Stash local changes and continue?"; then
      log_info "Update cancelled"
      exit 0
    fi
    git stash push -m "deploy.sh auto-stash before update"
  fi

  if ! git pull; then
    log_error "Failed to pull updates"
    exit 1
  fi

  # Optional pre-update backup
  if confirm "Create database backup before updating?"; then
    log_step "Creating pre-update backup..."
    if ! op_backup; then
      log_warn "Backup failed. Continue without backup?"
      if ! confirm "Continue anyway?"; then
        log_info "Update cancelled"
        exit 0
      fi
    fi
  fi

  log_step "Rebuilding containers..."
  $COMPOSE_CMD -f "$COMPOSE_FILE" up --build -d

  log_step "Waiting for services to be ready..."
  sleep 5

  op_run_migrations

  log_info "Update complete"
}

op_backup() {
  ensure_backup_dir

  local timestamp
  timestamp=$(date +%Y%m%d_%H%M%S)
  local backup_file="$BACKUP_DIR/hievents_${timestamp}.sql"

  log_step "Creating database backup..."

  if ! $RUNTIME exec "$(get_postgres_container)" pg_dump -U hievents -d hievents >"$backup_file"; then
    log_error "Backup failed"
    rm -f "$backup_file"
    exit 1
  fi

  if ! validate_backup_file "$backup_file"; then
    rm -f "$backup_file"
    exit 1
  fi

  log_info "Backup created: $backup_file"
}

op_restore() {
  local backups
  backups=$(get_backup_files)

  if [[ -z "$backups" ]]; then
    log_error "No backups found in $BACKUP_DIR"
    exit 1
  fi

  echo "Available backups:"
  echo "$backups" | head -10 | nl
  echo ""

  local backup_num
  read -rp "Enter number of backup to restore: " backup_num

  if ! [[ "$backup_num" =~ ^[0-9]+$ ]]; then
    log_error "Invalid input: must be a number"
    exit 1
  fi

  local backup_file
  backup_file=$(echo "$backups" | sed -n "${backup_num}p")

  if ! validate_backup_file "$backup_file"; then
    exit 1
  fi

  echo ""
  log_warn "This will DESTROY the current database and replace it with the backup."

  if ! confirm "Are you sure you want to continue?"; then
    log_info "Restore cancelled"
    exit 0
  fi

  log_step "Restoring from $(basename "$backup_file")..."

  if ! $RUNTIME exec -i "$(get_postgres_container)" psql -U hievents -d hievents <"$backup_file"; then
    log_error "Restore failed"
    exit 1
  fi

  log_info "Restore complete"
}

op_shell() {
  log_step "Opening shell in backend container..."
  $RUNTIME exec -it "$(get_backend_container)" sh
}

op_run_migrations() {
  log_step "Running database migrations..."

  if ! $RUNTIME exec "$(get_backend_container)" php artisan migrate --force; then
    log_error "Migrations failed"
    exit 1
  fi

  log_info "Migrations complete"
}

op_configure_autostart() {
  log_step "Configuring auto-start with systemd..."

  local service_dir="$HOME/.config/systemd/user"
  local service_file="$service_dir/hievents-backend.service"

  mkdir -p "$service_dir"

  # Create service file
  cat >"$service_file" <<EOF
[Unit]
Description=hievents Backend API
After=$RUNTIME.socket
Wants=$RUNTIME.socket

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=$SCRIPT_DIR
ExecStart=$COMPOSE_CMD -f $COMPOSE_FILE up -d --remove-orphans
ExecStop=$COMPOSE_CMD -f $COMPOSE_FILE down
ExecReload=$COMPOSE_CMD -f $COMPOSE_FILE up -d --remove-orphans

[Install]
WantedBy=default.target
EOF

  # Enable linger for user (start on boot without login)
  loginctl enable-linger "$USER"

  # Reload and enable service
  systemctl --user daemon-reload

  if ! systemctl --user enable hievents-backend.service; then
    log_error "Failed to enable service"
    exit 1
  fi

  log_info "Auto-start configured"
  echo ""
  echo "The backend will automatically start on system boot."
  echo -e "Check status: ${YELLOW}systemctl --user status hievents-backend.service${NC}"
}

#==============================================================================
# Menu
#==============================================================================

show_menu() {
  echo ""
  echo "What would you like to do?"
  echo ""
  echo "  0) Generate/Regenerate .env configuration file"
  echo ""
  echo "  1) First time deploy (build + start)"
  echo "  2) Start existing containers"
  echo "  3) Stop containers"
  echo "  4) Restart containers"
  echo "  5) View logs"
  echo "  6) Update (pull + rebuild + restart)"
  echo "  7) Database backup"
  echo "  8) Database restore"
  echo "  9) Shell into backend"
  echo "  10) Run migrations"
  echo "  11) Configure auto-start on boot (systemd)"
  echo ""
}

#==============================================================================
# Main
#==============================================================================

main() {
  echo -e "${GREEN}=== hievents Backend Deployment ===${NC}"
  echo ""

  validate_not_root
  validate_container_runtime

  # Check if .env exists and offer to create it if not
  if [[ ! -f "$BACKEND_DIR/.env" ]]; then
    log_warn "Configuration file not found: $BACKEND_DIR/.env"
    echo ""
    echo "This is your first time setup. Let's create the configuration file."
    echo ""
    generate_env_file
    exit 0
  fi

  validate_env_file
  ensure_backup_dir

  log_info "Using container runtime: $RUNTIME"
  log_info "Configuration loaded from: backend/.env"
  echo ""

  show_menu

  local choice
  read -rp "Enter choice [0-11]: " choice

  # Allow 0 for env generation, validate 1-11 for other operations
  if [[ "$choice" == "0" ]]; then
    generate_env_file
    exit 0
  fi

  if ! validate_menu_choice "$choice"; then
    exit 1
  fi

  case $choice in
  1) op_first_deploy ;;
  2) op_start ;;
  3) op_stop ;;
  4) op_restart ;;
  5) op_logs ;;
  6) op_update ;;
  7) op_backup ;;
  8) op_restore ;;
  9) op_shell ;;
  10) op_run_migrations ;;
  11) op_configure_autostart ;;
  esac

  echo ""
}

main "$@"
