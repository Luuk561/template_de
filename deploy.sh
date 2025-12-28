#!/bin/bash

#==============================================================================
# DEPLOY WRAPPER - Run deployment in background
# Je kunt je laptop dichtdoen terwijl dit draait!
#==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_SCRIPT="$SCRIPT_DIR/deploy-multi-site.sh"
LOG_DIR="/home/forge/deployment-logs"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
LOG_FILE="$LOG_DIR/deploy-${TIMESTAMP}.log"
PID_FILE="/tmp/deploy-multi-site.pid"

# Kleuren
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  MULTI-SITE DEPLOYMENT STARTER${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if deployment is already running
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE")
    if ps -p "$OLD_PID" > /dev/null 2>&1; then
        echo -e "${YELLOW}Deployment is already running (PID: $OLD_PID)${NC}"
        echo ""
        echo "View live progress with:"
        echo "  tail -f $LOG_DIR/deploy-*.log"
        echo ""
        echo "Or kill the running deployment:"
        echo "  kill $OLD_PID"
        exit 1
    else
        # Old PID file exists but process is dead
        rm -f "$PID_FILE"
    fi
fi

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"

echo -e "${GREEN}Starting deployment in background...${NC}"
echo ""
echo "Log file: $LOG_FILE"
echo "PID file: $PID_FILE"
echo ""

# Start deployment in background with nohup
nohup "$DEPLOY_SCRIPT" "$@" > "$LOG_FILE" 2>&1 &
DEPLOY_PID=$!

# Save PID
echo "$DEPLOY_PID" > "$PID_FILE"

echo -e "${GREEN}Deployment started! (PID: $DEPLOY_PID)${NC}"
echo ""
echo -e "${BLUE}You can now close your laptop!${NC}"
echo ""
echo "Commands:"
echo "  ${GREEN}# Watch live progress:${NC}"
echo "  tail -f $LOG_FILE"
echo ""
echo "  ${GREEN}# Check if still running:${NC}"
echo "  ps -p $DEPLOY_PID"
echo ""
echo "  ${GREEN}# Stop deployment:${NC}"
echo "  kill $DEPLOY_PID"
echo ""
echo "  ${GREEN}# View final result:${NC}"
echo "  cat $LOG_FILE"
echo ""

# Show first few lines of output
sleep 2
echo -e "${YELLOW}First lines of output:${NC}"
head -20 "$LOG_FILE" 2>/dev/null || echo "Waiting for output..."
echo ""
echo -e "${BLUE}Deployment running in background...${NC}"
