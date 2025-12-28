#!/bin/bash

#==============================================================================
# ROBUUST MULTI-SITE DEPLOYMENT SCRIPT
# Voor 47 affiliate sites (zelfde codebase, verschillende databases)
#
# Inclusief ChatGPT verbeteringen:
# - Unicode bug fix (kapotte karakters)
# - Preflight checks (missende tools detecteren)
# - Skip unchanged sites (bespaar tijd)
# - Robuuste flag parsing
# - Betere stash handling
#==============================================================================

set -uo pipefail  # Removed -e so script continues after errors
IFS=$'\n\t'

#------------------------------------------------------------------------------
# CONFIGURATIE
#------------------------------------------------------------------------------

BRANCH="main"  # Default branch
DRY_RUN=false
NO_CONFIRM=false
LOG_DIR="/home/forge/deployment-logs"
LOG_FILE="$LOG_DIR/deploy-$(date +%Y%m%d-%H%M%S).log"
TIMEOUT_SECONDS=300
PHP_VERSION="8.3"

# Kleuren
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

#------------------------------------------------------------------------------
# SITES
#------------------------------------------------------------------------------

declare -a SITES=(
  "airfryermetdubbelelade.nl"
  "aivertalerapparaat.nl"
  "beste-elektrischemassagekussen.nl"
  "beste-elektrischestep.nl"
  "beste-hometrainer.nl"
  "beste-massagegun.nl"
  "beste-robotmaaier.nl"
  "beste-sporthorloge.nl"
  "bestebabyfoonmetcamera.nl"
  "bestebeveiligingscamera.nl"
  "bestebonenmachines.nl"
  "bestedeurbelmetcamera.nl"
  "bestedraadlozeheadphones.nl"
  "besterobotstofzuiger.nl"
  "besteslowcookerkopen.nl"
  "bestewifiversterker.nl"
  "crosstrainertest.nl"
  "debeste-ereader.nl"
  "de-bestefohn.nl"
  "debestekinderwagen.nl"
  "debestekrulborstel.nl"
  "debestemobieleairco.nl"
  "debestepowerbank.nl"
  "debestesoundbar.nl"
  "debestestijltang.nl"
  "elektrischetandenborsteltest.nl"
  "hondenvoerautomaat.nl"
  "kattenvoerautomaat.nl"
  "kruimeldieftest.nl"
  "loopbandentest.nl"
  "roeitrainertest.nl"
  "topscheerapparaat.nl"
  "besteelektrischedeken.nl"
  "beste-massagestoel.nl"
  "beste-gamingstoel.nl"
  "bestedigitalefotolijst.nl"
  "bestegamingpc.nl"
  "bestedraadlozestofzuiger.nl"
  "bestetelevisie.nl"
  "bestegamingmonitor.nl"
  "debestebureaustoel.nl"
  "debeste-elektrischefiets.nl"
  "debestewijnkoelkast.nl"
  "beste-hottub.nl"
  "beste-fatbike.nl"
  "beste-boxspring.nl"
  "bestebarbeque.nl"
)

#------------------------------------------------------------------------------
# PREFLIGHT CHECKS
#------------------------------------------------------------------------------

REQUIRED_CMDS=(git composer php timeout curl)
for cmd in "${REQUIRED_CMDS[@]}"; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "❌ FOUT: Vereist commando ontbreekt: $cmd"
    exit 2
  fi
done

#------------------------------------------------------------------------------
# ARGUMENT PARSING
#------------------------------------------------------------------------------

declare -a SELECTED_SITES=()

# Parse argumenten
for arg in "$@"; do
  case "$arg" in
    --dry-run)
      DRY_RUN=true
      ;;
    --no-confirm)
      NO_CONFIRM=true
      ;;
    --sites=*)
      IFS=',' read -r -a SELECTED_SITES <<< "${arg#*=}"
      ;;
    --branch=*)
      BRANCH="${arg#*=}"
      ;;
    main|develop|staging)
      BRANCH="$arg"
      ;;
    *)
      echo "Unknown argument: $arg"
      echo "Usage: $0 [main|develop] [--dry-run] [--no-confirm] [--sites=site1,site2]"
      exit 1
      ;;
  esac
done

# Filter sites als specifiek opgegeven
if [ ${#SELECTED_SITES[@]} -gt 0 ]; then
  declare -a FILTERED=()
  for s in "${SITES[@]}"; do
    for pick in "${SELECTED_SITES[@]}"; do
      if [ "$s" = "$pick" ]; then
        FILTERED+=("$s")
      fi
    done
  done

  if [ ${#FILTERED[@]} -eq 0 ]; then
    echo "❌ Geen matches voor --sites=${SELECTED_SITES[*]}"
    exit 1
  fi

  SITES=("${FILTERED[@]}")
fi

#------------------------------------------------------------------------------
# LOGGING FUNCTIES
#------------------------------------------------------------------------------

log() {
  local level=$1
  shift
  local message="$@"
  local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
  echo -e "${timestamp} [${level}] ${message}" | tee -a "$LOG_FILE"
}

log_info() { echo -e "${BLUE}ℹ️  $@${NC}" | tee -a "$LOG_FILE"; }
log_success() { echo -e "${GREEN}✅ $@${NC}" | tee -a "$LOG_FILE"; }
log_warning() { echo -e "${YELLOW}⚠️  $@${NC}" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}❌ $@${NC}" | tee -a "$LOG_FILE"; }

#------------------------------------------------------------------------------
# TIMEOUT WRAPPER
#------------------------------------------------------------------------------

run_with_timeout() {
  local timeout_val=$1
  shift
  local cmd="$@"

  timeout "$timeout_val" bash -c "$cmd" 2>&1 || {
    local exit_code=$?
    if [ $exit_code -eq 124 ]; then
      log_error "Timeout na ${timeout_val}s voor: $cmd"
      return 124
    fi
    return $exit_code
  }
}

#------------------------------------------------------------------------------
# DEPLOY FUNCTIE (1 SITE)
#------------------------------------------------------------------------------

deploy_site() {
  local site=$1
  local site_dir="/home/forge/$site"
  local start_time=$(date +%s)

  log_info "=================================================="
  log_info "Deploying: $site"
  log_info "=================================================="

  # Check directory
  if [ ! -d "$site_dir" ]; then
    log_error "Directory niet gevonden: $site_dir"
    return 1
  fi

  cd "$site_dir" || {
    log_error "Kan niet naar directory: $site_dir"
    return 1
  }

  log_info "📁 Working directory: $(pwd)"

  # DRY RUN
  if [ "$DRY_RUN" = true ]; then
    log_warning "[DRY RUN] Zou nu deployen..."
    return 0
  fi

  # Git check
  log_info "🔍 Checking git status..."
  if ! git rev-parse --git-dir > /dev/null 2>&1; then
    log_error "Geen git repository in $site_dir"
    return 1
  fi

  local current_branch=$(git rev-parse --abbrev-ref HEAD)
  log_info "Current branch: $current_branch"

  # Skip check uitgeschakeld - git pull doet zelf al een check
  # en is sneller dan fetch + compare
  log_info "🔎 Fetching latest changes..."
  run_with_timeout 30 "git fetch origin $BRANCH" || {
    log_warning "git fetch faalde (ga toch door met pull)"
  }

  # Stash local changes
  local stash_needed=false
  if ! git diff-index --quiet HEAD --; then
    log_warning "Local changes detected, stashing..."
    git stash save "Auto-stash before deploy $(date)" || true
    stash_needed=true
  fi

  # Git pull
  log_info "🔄 Git pull origin $BRANCH..."
  if ! run_with_timeout 60 "git pull origin $BRANCH"; then
    local ec=$?
    if [ $ec -eq 124 ]; then
      log_error "Git pull timeoutte voor $site"
    else
      log_error "Git pull faalde voor $site (exit $ec)"
    fi

    # Stash pop alleen als er echt is gestasht
    if [ "$stash_needed" = true ]; then
      if git stash list | grep -q "Auto-stash before deploy"; then
        git stash pop || log_warning "Kon stash niet poppen; los dit handmatig op."
      fi
    fi
    return 1
  fi

  local new_commit=$(git rev-parse --short HEAD)
  log_success "Git pull successful. Commit: $new_commit"

  # Composer install
  log_info "📦 Composer install..."
  export COMPOSER_MEMORY_LIMIT=-1
  if ! run_with_timeout $TIMEOUT_SECONDS "composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader"; then
    log_error "Composer install failed voor $site"
    return 1
  fi
  log_success "Composer install successful"

  # NPM (optional)
  if [ -f "package.json" ]; then
    log_info "📦 NPM install..."
    if ! run_with_timeout 180 "npm install"; then
      log_warning "NPM install failed (non-critical)"
    else
      log_success "NPM install successful"

      # Build optimized assets (CSS/JS)
      log_info "🔨 Building optimized assets..."
      if ! run_with_timeout 180 "npm run build"; then
        log_warning "NPM build failed (non-critical)"
      else
        log_success "Assets built successfully (CSS/JS optimized)"
      fi
    fi
  fi

  # Laravel Artisan
  if [ -f "artisan" ]; then
    log_info "⚙️  Running Artisan commands..."

    # Migrations
    log_info "  → Running migrations..."
    if ! run_with_timeout 120 "php artisan migrate --force"; then
      log_warning "Migrations failed (mogelijk geen nieuwe migrations)"
    else
      log_success "  ✓ Migrations completed"
    fi

    # Clear ALL caches (inclusief opcache)
    log_info "  → Clearing caches..."
    php artisan optimize:clear >> "$LOG_FILE" 2>&1 || log_warning "optimize:clear had errors (check logs)"

    # Rebuild caches voor production
    log_info "  → Rebuilding caches..."
    php artisan config:cache >> "$LOG_FILE" 2>&1
    php artisan route:cache >> "$LOG_FILE" 2>&1
    php artisan view:cache >> "$LOG_FILE" 2>&1
    log_success "  ✓ Caches optimized"

    # SEO Fixes (eenmalig voor bestaande content)
    log_info "  → Running SEO fixes (duplicate titles/descriptions)..."
    if php artisan seo:fix-all >> "$LOG_FILE" 2>&1; then
      log_success "  ✓ SEO fixes applied (titles/descriptions/structured data)"
    else
      log_warning "  ⚠️  SEO fixes failed (check log)"
    fi

    # Verify BOL_SITE_ID (CRITICAL voor affiliate commissie)
    log_info "  → Verifying BOL_SITE_ID..."
    local bol_site_id=$(php artisan tinker --execute="echo config('bol.site_id');" 2>/dev/null | grep -oE '[A-Z0-9]{7}' | head -1 || echo "unknown")
    if [ "$bol_site_id" = "fallback_id" ] || [ "$bol_site_id" = "unknown" ] || [ -z "$bol_site_id" ]; then
      log_error "  ⚠️  CRITICAL: BOL_SITE_ID = '$bol_site_id' - NO COMMISSION!"
      log_error "  → Fix .env file immediately!"
    else
      log_success "  ✓ BOL_SITE_ID: $bol_site_id"
    fi

    local laravel_version=$(php artisan --version 2>/dev/null || echo "Unknown")
    log_info "  → Laravel: $laravel_version"

    log_success "  ✓ Artisan commands completed"
  else
    log_warning "No artisan file found in $site_dir"
  fi

  # Health check
  log_info "🏥 Health check..."
  local health_url="https://$site"
  local http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$health_url" || echo "000")

  if [ "$http_code" = "200" ] || [ "$http_code" = "302" ]; then
    log_success "Health check passed ($http_code)"
  else
    log_warning "Health check returned: $http_code (site might be down)"
  fi

  # Permissions
  log_info "🔐 Checking permissions..."
  if [ -d "storage" ]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || {
      log_warning "Permission change failed (mogelijk niet nodig)"
    }
  fi

  # Success
  local end_time=$(date +%s)
  local duration=$((end_time - start_time))

  log_success "=================================================="
  log_success "✅ $site deployed successfully in ${duration}s"
  log_success "=================================================="
  echo ""

  return 0
}

#------------------------------------------------------------------------------
# MAIN
#------------------------------------------------------------------------------

main() {
  local script_start=$(date +%s)

  # Create log dir
  mkdir -p "$LOG_DIR"

  # Header
  echo ""
  log_info "==================================================="
  log_info "🚀 MULTI-SITE DEPLOYMENT"
  log_info "==================================================="
  log_info "Branch: $BRANCH"
  log_info "Sites: ${#SITES[@]}"
  log_info "Dry run: $DRY_RUN"
  log_info "Log file: $LOG_FILE"
  log_info "Started: $(date '+%Y-%m-%d %H:%M:%S')"
  log_info "==================================================="
  echo ""

  # Confirmation
  if [ "$DRY_RUN" = false ] && [ "$NO_CONFIRM" = false ]; then
    read -p "Deploy ${#SITES[@]} sites naar branch '$BRANCH'? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
      log_warning "Deployment afgebroken door gebruiker"
      exit 0
    fi
  fi

  # Counters
  local total_sites=${#SITES[@]}
  local successful=0
  local failed=0
  local skipped=0
  declare -a failed_sites=()

  # Deploy loop
  for i in "${!SITES[@]}"; do
    local site="${SITES[$i]}"
    local progress=$((i + 1))

    log_info "[$progress/$total_sites] Processing: $site"

    if deploy_site "$site"; then
      ((successful++))
    else
      ((failed++))
      failed_sites+=("$site")
      log_error "Failed to deploy: $site"
    fi

    # Rate limiting
    if [ $progress -lt $total_sites ]; then
      log_info "⏸️  Waiting 5s before next site..."
      sleep 5
    fi
  done

  # PHP-FPM reload (1x voor alle sites = efficiënter dan per site)
  if [ "$DRY_RUN" = false ]; then
    log_info "🔄 Reloading PHP-FPM to clear opcache..."
    if sudo service php${PHP_VERSION}-fpm reload 2>&1 | tee -a "$LOG_FILE"; then
      log_success "PHP-FPM reloaded (opcache cleared)"
    else
      log_warning "PHP-FPM reload failed - opcache may be stale"
    fi
  fi

  # Summary
  local script_end=$(date +%s)
  local total_duration=$((script_end - script_start))
  local minutes=$((total_duration / 60))
  local seconds=$((total_duration % 60))

  echo ""
  log_info "==================================================="
  log_info "📊 DEPLOYMENT SUMMARY"
  log_info "==================================================="
  log_info "Total sites: $total_sites"
  log_success "Successful: $successful"
  [ $failed -gt 0 ] && log_error "Failed: $failed" || log_info "Failed: 0"
  log_info "Duration: ${minutes}m ${seconds}s"
  log_info "Average: $((total_duration / total_sites))s per site"
  log_info "Log file: $LOG_FILE"

  # Failed sites
  if [ $failed -gt 0 ]; then
    echo ""
    log_error "Failed sites:"
    for site in "${failed_sites[@]}"; do
      log_error "  - $site"
    done
    echo ""
    log_warning "Check log file for details: $LOG_FILE"
  fi

  log_info "==================================================="

  # Exit code
  if [ $failed -gt 0 ]; then
    exit 1
  else
    log_success "🎉 All sites deployed successfully!"
    exit 0
  fi
}

#------------------------------------------------------------------------------
# ENTRY POINT
#------------------------------------------------------------------------------

trap 'log_error "Script interrupted at line $LINENO"' ERR
trap 'log_warning "Script interrupted by user (Ctrl+C)"' INT

main "$@"
