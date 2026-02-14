#!/bin/bash
# Sync plugin from dev folder to Moodle installation and rebuild AMD.
# Usage: ./deploy.sh

DEV_DIR="$(cd "$(dirname "$0")" && pwd)"
MOODLE_DIR="/Applications/MAMP/htdocs/moodle"
PLUGIN_DIR="$MOODLE_DIR/local/unifiedgrader"

echo "Syncing $DEV_DIR → $PLUGIN_DIR"
rsync -av --delete \
  --exclude='.claude' \
  --exclude='amd/build' \
  --exclude='.eslintrc' \
  --exclude='deploy.sh' \
  --exclude='.git' \
  "$DEV_DIR/" "$PLUGIN_DIR/"

echo ""
echo "Building AMD modules..."
cd "$MOODLE_DIR" && npx grunt amd --root=local/unifiedgrader

echo ""
echo "Copying built files back to dev folder..."
cp -R "$PLUGIN_DIR/amd/build" "$DEV_DIR/amd/"

echo ""
echo "Done!"
