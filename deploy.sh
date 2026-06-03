#!/bin/bash
# Sync plugin from dev folder to Moodle installation and rebuild AMD.
# Usage: ./deploy.sh

DEV_DIR="$(cd "$(dirname "$0")" && pwd)"
MOODLE_DIR="/Applications/MAMP/htdocs/moodle"
PLUGIN_DIR="$MOODLE_DIR/local/unifiedgrader"

# Defense-in-depth: verify SHA-256 of bundled third-party libs against the
# values recorded in thirdpartylibs.xml. Catches accidental or malicious
# tampering of files under thirdparty/ before we ship them to Moodle.
# Skips gracefully if xmllint or shasum aren't available.
verify_thirdparty_integrity() {
    local xml="$DEV_DIR/thirdpartylibs.xml"
    if ! command -v xmllint >/dev/null 2>&1 || ! command -v shasum >/dev/null 2>&1; then
        echo "Skipping third-party integrity check (xmllint or shasum not installed)."
        return 0
    fi
    if [ ! -f "$xml" ]; then
        echo "Skipping third-party integrity check (thirdpartylibs.xml not found)."
        return 0
    fi

    local count
    count=$(xmllint --xpath "count(//library)" "$xml" 2>/dev/null || echo 0)
    if [ "$count" = "0" ] || [ -z "$count" ]; then
        echo "Skipping third-party integrity check (no <library> entries)."
        return 0
    fi

    local failed=0
    local checked=0
    for i in $(seq 1 "$count"); do
        local location
        location=$(xmllint --xpath "string(//library[$i]/location)" "$xml" 2>/dev/null)
        local nfiles
        nfiles=$(xmllint --xpath "count(//library[$i]/sha256/file)" "$xml" 2>/dev/null || echo 0)
        if [ "$nfiles" = "0" ] || [ -z "$nfiles" ]; then
            continue
        fi
        for j in $(seq 1 "$nfiles"); do
            local relpath expected actual abs
            relpath=$(xmllint --xpath "string(//library[$i]/sha256/file[$j]/@path)" "$xml" 2>/dev/null)
            expected=$(xmllint --xpath "string(//library[$i]/sha256/file[$j])" "$xml" 2>/dev/null | tr -d ' \t\r\n')
            abs="$DEV_DIR/$location/$relpath"
            if [ ! -f "$abs" ]; then
                echo "ERROR: third-party file missing: $location/$relpath"
                failed=$((failed + 1))
                continue
            fi
            actual=$(shasum -a 256 "$abs" | awk '{print $1}')
            if [ "$actual" != "$expected" ]; then
                echo "ERROR: SHA-256 mismatch for $location/$relpath"
                echo "  expected: $expected"
                echo "  actual:   $actual"
                failed=$((failed + 1))
            fi
            checked=$((checked + 1))
        done
    done

    if [ "$failed" -gt 0 ]; then
        echo "Third-party integrity check FAILED ($failed file(s) tampered or missing). Aborting deploy."
        return 1
    fi
    echo "Verified third-party integrity ($checked file(s) match thirdpartylibs.xml)."
    return 0
}

if ! verify_thirdparty_integrity; then
    exit 1
fi

echo ""
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
echo "Purging Moodle caches..."
php "$MOODLE_DIR/admin/cli/purge_caches.php"

echo ""
echo "Done!"
