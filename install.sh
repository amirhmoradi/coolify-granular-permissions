#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# Coolify Granular Permissions - Installer
# =============================================================================
# Installs the granular permissions addon on a running Coolify v4 instance.
# Supports both pre-built GHCR image (default) and local build methods.
#
# Usage:
#   bash install.sh              # Interactive install (GHCR image)
#   bash install.sh --local      # Interactive install (local build)
#   bash install.sh --unattended # Non-interactive with defaults
# =============================================================================

COOLIFY_BASE="/data/coolify"
COOLIFY_SOURCE="${COOLIFY_BASE}/source"
COMPOSE_FILE="${COOLIFY_SOURCE}/docker-compose.yml"
CUSTOM_COMPOSE="${COOLIFY_SOURCE}/docker-compose.custom.yml"
UPGRADE_SCRIPT="${COOLIFY_SOURCE}/upgrade.sh"
ENV_FILE="${COOLIFY_SOURCE}/.env"
GHCR_IMAGE="ghcr.io/amirhmoradi/coolify-granular-permissions:latest"
LOCAL_IMAGE_NAME="coolify-granular-permissions:local"
BACKUP_SUFFIX=".backup.$(date +%Y%m%d_%H%M%S)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Parse arguments
METHOD="ghcr"
UNATTENDED=false
for arg in "$@"; do
    case "$arg" in
        --local)  METHOD="local" ;;
        --unattended) UNATTENDED=true ;;
        --help|-h)
            echo "Usage: bash install.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --local       Build image locally instead of pulling from GHCR"
            echo "  --unattended  Non-interactive install with default settings"
            echo "  --help, -h    Show this help message"
            exit 0
            ;;
    esac
done

# --- Helper functions ---

info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; }
step()    { echo -e "\n${CYAN}==>${NC} $*"; }

confirm() {
    if [ "$UNATTENDED" = true ]; then
        return 0
    fi
    local prompt="${1:-Continue?} [y/N] "
    read -r -p "$prompt" response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) return 1 ;;
    esac
}

# --- Pre-flight checks ---

step "Checking prerequisites..."

# Must be root
if [ "$(id -u)" -ne 0 ]; then
    error "This script must be run as root (or with sudo)."
    exit 1
fi
success "Running as root"

# Docker must be installed
if ! command -v docker &>/dev/null; then
    error "Docker is not installed. Please install Docker first."
    exit 1
fi
success "Docker is available"

# Docker Compose must be available
if ! docker compose version &>/dev/null; then
    error "Docker Compose (v2) is not available. Please install Docker Compose."
    exit 1
fi
success "Docker Compose is available"

# Check for Coolify installation
if [ ! -f "$COMPOSE_FILE" ]; then
    error "Coolify installation not found at ${COOLIFY_SOURCE}"
    echo ""
    echo "Expected docker-compose.yml at: ${COMPOSE_FILE}"
    echo ""
    echo "If Coolify is not installed, install it first:"
    echo "  curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash"
    echo ""
    echo "If Coolify is installed in a different location, set COOLIFY_SOURCE:"
    echo "  COOLIFY_SOURCE=/path/to/coolify/source bash install.sh"
    exit 1
fi
success "Coolify installation found at ${COOLIFY_SOURCE}"

# Check for upgrade.sh
if [ ! -f "$UPGRADE_SCRIPT" ]; then
    warn "upgrade.sh not found at ${UPGRADE_SCRIPT}"
    warn "Will use 'docker compose up -d' to restart instead."
fi

# Check that Coolify is actually running
if ! docker ps --format '{{.Names}}' | grep -q "coolify"; then
    warn "Coolify container does not appear to be running."
    if ! confirm "Continue anyway?"; then
        echo "Aborted."
        exit 1
    fi
else
    success "Coolify container is running"
fi

# Check for existing custom compose file
if [ -f "$CUSTOM_COMPOSE" ]; then
    warn "A docker-compose.custom.yml already exists at ${CUSTOM_COMPOSE}"
    echo ""
    echo "Existing content:"
    cat "$CUSTOM_COMPOSE"
    echo ""
    if ! confirm "Overwrite the existing file?"; then
        echo "Aborted. Please back up or remove the existing file first."
        exit 1
    fi
    cp "$CUSTOM_COMPOSE" "${CUSTOM_COMPOSE}${BACKUP_SUFFIX}"
    info "Backed up existing file to docker-compose.custom.yml${BACKUP_SUFFIX}"
fi

# --- Choose install method ---

if [ "$UNATTENDED" = false ] && [ "$METHOD" = "ghcr" ]; then
    echo ""
    echo "Installation methods:"
    echo "  1) Pre-built image from GHCR (recommended, faster)"
    echo "  2) Build image locally (requires cloning the repo)"
    echo ""
    read -r -p "Choose method [1]: " choice
    case "$choice" in
        2) METHOD="local" ;;
        *) METHOD="ghcr" ;;
    esac
fi

# --- Install ---

if [ "$METHOD" = "ghcr" ]; then
    step "Pulling pre-built image from GHCR..."
    if ! docker pull "$GHCR_IMAGE"; then
        error "Failed to pull image. Check your internet connection and registry access."
        exit 1
    fi
    success "Image pulled: ${GHCR_IMAGE}"

    IMAGE_TO_USE="$GHCR_IMAGE"

elif [ "$METHOD" = "local" ]; then
    step "Building image locally..."

    # Find the package directory (where this script lives)
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

    if [ ! -f "${SCRIPT_DIR}/docker/Dockerfile" ]; then
        error "Dockerfile not found at ${SCRIPT_DIR}/docker/Dockerfile"
        error "Make sure you're running this script from the package repository root."
        exit 1
    fi

    # Ask for Coolify version
    COOLIFY_VERSION="latest"
    if [ "$UNATTENDED" = false ]; then
        read -r -p "Coolify version to build against [latest]: " version_input
        if [ -n "$version_input" ]; then
            COOLIFY_VERSION="$version_input"
        fi
    fi

    info "Building with COOLIFY_VERSION=${COOLIFY_VERSION}..."
    if ! docker build \
        --build-arg "COOLIFY_VERSION=${COOLIFY_VERSION}" \
        -t "$LOCAL_IMAGE_NAME" \
        -f "${SCRIPT_DIR}/docker/Dockerfile" \
        "$SCRIPT_DIR"; then
        error "Docker build failed."
        exit 1
    fi
    success "Image built: ${LOCAL_IMAGE_NAME}"

    IMAGE_TO_USE="$LOCAL_IMAGE_NAME"
fi

# --- Deploy docker-compose.custom.yml ---

step "Deploying docker-compose.custom.yml..."

cat > "$CUSTOM_COMPOSE" << EOF
# Coolify Granular Permissions - Auto-generated by install.sh
# Installed: $(date -u +"%Y-%m-%dT%H:%M:%SZ")

services:
  coolify:
    image: ${IMAGE_TO_USE}
    environment:
      - COOLIFY_GRANULAR_PERMISSIONS=true
EOF

success "Created ${CUSTOM_COMPOSE}"

# --- Set environment variable ---

step "Configuring environment..."

if [ -f "$ENV_FILE" ]; then
    if grep -q "COOLIFY_GRANULAR_PERMISSIONS" "$ENV_FILE"; then
        # Update existing value
        sed -i 's/COOLIFY_GRANULAR_PERMISSIONS=.*/COOLIFY_GRANULAR_PERMISSIONS=true/' "$ENV_FILE"
        info "Updated COOLIFY_GRANULAR_PERMISSIONS=true in .env"
    else
        echo "COOLIFY_GRANULAR_PERMISSIONS=true" >> "$ENV_FILE"
        info "Added COOLIFY_GRANULAR_PERMISSIONS=true to .env"
    fi
else
    warn ".env file not found at ${ENV_FILE}. Feature flag will rely on docker-compose.custom.yml."
fi

success "Environment configured"

# --- Restart Coolify ---

step "Restarting Coolify stack..."

if [ -f "$UPGRADE_SCRIPT" ]; then
    info "Running upgrade.sh to restart the stack..."
    if ! bash "$UPGRADE_SCRIPT"; then
        warn "upgrade.sh exited with non-zero status. Trying docker compose directly..."
        cd "$COOLIFY_SOURCE"
        docker compose up -d
    fi
else
    cd "$COOLIFY_SOURCE"
    docker compose up -d
fi

success "Coolify stack restarted"

# --- Wait for Coolify to be ready ---

step "Waiting for Coolify to be ready..."

MAX_WAIT=60
WAITED=0
while [ $WAITED -lt $MAX_WAIT ]; do
    if docker ps --format '{{.Names}}' | grep -q "coolify" && \
       docker inspect --format='{{.State.Health.Status}}' coolify 2>/dev/null | grep -q "healthy"; then
        break
    fi
    # Also accept running state if no health check
    if docker ps --format '{{.Names}}\t{{.Status}}' | grep "coolify" | grep -q "Up"; then
        break
    fi
    sleep 2
    WAITED=$((WAITED + 2))
    echo -n "."
done
echo ""

if [ $WAITED -ge $MAX_WAIT ]; then
    warn "Timed out waiting for Coolify. It may still be starting up."
    warn "Check with: docker logs coolify --tail 50"
else
    success "Coolify is running"
fi

# --- Verify installation ---

step "Verifying installation..."

# Check the running image
RUNNING_IMAGE=$(docker inspect coolify --format='{{.Config.Image}}' 2>/dev/null || echo "unknown")
info "Running image: ${RUNNING_IMAGE}"

if [ "$RUNNING_IMAGE" = "$IMAGE_TO_USE" ]; then
    success "Correct image is running"
else
    warn "Running image doesn't match expected. Expected: ${IMAGE_TO_USE}"
    warn "The upgrade.sh script may have pulled a different image."
    warn "Check your docker-compose.custom.yml is being loaded."
fi

# Check if package is loaded
if docker exec coolify php artisan package:discover 2>/dev/null | grep -q "coolify-granular-permissions"; then
    success "Package is discovered by Laravel"
else
    warn "Could not verify package discovery. This is normal if Coolify is still starting."
fi

# Check migration status
if docker exec coolify php artisan migrate:status 2>/dev/null | grep -q "project_user"; then
    success "Database migrations have run"
else
    info "Migrations may not have run yet. They will run automatically on next container start."
    info "Or run manually: docker exec coolify php artisan migrate --path=vendor/amirhmoradi/coolify-granular-permissions/database/migrations --force"
fi

# --- Done ---

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Installation Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "What was done:"
echo "  - Custom image: ${IMAGE_TO_USE}"
echo "  - Created: ${CUSTOM_COMPOSE}"
echo "  - Set: COOLIFY_GRANULAR_PERMISSIONS=true"
echo "  - Restarted Coolify stack"
echo ""
echo "Next steps:"
echo "  1. Log into Coolify as an admin/owner"
echo "  2. Navigate to Team > Admin to see the Access Matrix"
echo "  3. Configure per-user project/environment permissions"
echo ""
echo "To uninstall, run:"
echo "  bash uninstall.sh"
echo ""
echo "For more information, see: docs/installation.md"
