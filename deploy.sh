#!/bin/bash
# Sync plugin from dev folder to Moodle installation and rebuild AMD.
#
# Usage:
#   ./deploy.sh                 Deploy to every install in MOODLE_DIRS.
#   ./deploy.sh --zip           Deploy, then build a release zip.
#   ./deploy.sh --zip-only      Build the release zip; deploy nothing.
#   ./deploy.sh --zip --force   Build even with uncommitted changes.
#   ./deploy.sh --zip --slim    Leave tests/ out (production only; the
#                               Marketplace package should keep them).
#   ./deploy.sh --zip --suffix=212
#                               Override the version suffix in the zip's
#                               filename (default: the release with its dots
#                               removed, so 2.12.0 becomes 2120).
#
# Zipping is opt-in because a deploy happens on every test run and a release
# does not.

DEV_DIR="$(cd "$(dirname "$0")" && pwd)"

DO_DEPLOY=1
DO_ZIP=0
ZIP_SUFFIX=""
FORCE=0
SLIM=0

for arg in "$@"; do
    case "$arg" in
        --zip)        DO_ZIP=1 ;;
        --zip-only)   DO_ZIP=1; DO_DEPLOY=0 ;;
        --suffix=*)   ZIP_SUFFIX="${arg#--suffix=}" ;;
        --force)      FORCE=1 ;;
        --slim)       SLIM=1 ;;
        -h|--help)
            sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unknown option: $arg (try --help)"
            exit 1
            ;;
    esac
done

# Every Moodle install to deploy into, in order. The first is the BUILD host:
# AMD is compiled there once and the result copied back to the dev folder, then
# shipped to the rest as-is, so every install runs byte-identical built JS and
# the artifacts committed to the repo are the ones production will run.
#
# The plugin requires Moodle 5.1 or later, so the 5.0.7 install is no longer a
# target. Add a 5.1 or 5.2 install here if one is set up locally.
MOODLE_DIRS=(
    "/Users/mathieu/Sites/moodle/dev53"     # Moodle 5.3 — the release we develop on.
)

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

# Build an installable release zip from the committed tree.
#
# git archive rather than a copy-and-delete: it takes only tracked files, so
# vendor/ and .git/ cannot leak in, and the rest of the exclusions live in
# .gitattributes as export-ignore instead of in someone's memory. The flip side
# is that it archives HEAD, so uncommitted work is not in the zip - deliberate
# for a release, but worth saying out loud.
#
# The directory inside the zip MUST be "unifiedgrader". Moodle's installer
# compares it against the component declared in version.php:
#
#     if ($reqname !== $this->rootdir) { ... 'componentmismatchname' ... }
#
# so a root named local_unifiedgrader, or one carrying a version suffix, is
# rejected. The suffix belongs in the filename only. A zip built by Finder's
# "Compress" also fails, because the __MACOSX folder it adds is a second root
# directory and the validator insists on exactly one.
build_release_zip() {
    local release suffix zipname zippath

    # Parsed, not executed. version.php refers to Moodle constants such as
    # MATURITY_STABLE that do not exist outside Moodle, and including it raises
    # a fatal that PHP prints on STDOUT - which lands in $release and corrupts
    # the filename. The regex lives in PHP rather than grep because in an
    # extended regular expression the leading $ of $plugin is an anchor.
    release=$(php -d display_errors=0 -r '
        $src = file_get_contents($argv[1]);
        if (preg_match("/\\\$plugin->release\\s*=\\s*\\x27([^\\x27]+)\\x27/", $src, $m)) {
            echo $m[1];
        }
    ' "$DEV_DIR/version.php" 2>/dev/null)
    if [ -z "$release" ]; then
        echo "Could not read \$plugin->release from version.php. Aborting."
        return 1
    fi

    suffix="$ZIP_SUFFIX"
    [ -z "$suffix" ] && suffix="${release//./}"

    # Refuse anything that is not a plain version-ish token. The suffix ends up
    # in a filesystem path, and an earlier version of this function derived it
    # from unvalidated output and then ran rm -f on the result - never again.
    if ! printf '%s' "$suffix" | grep -qE '^[A-Za-z0-9._-]+$'; then
        echo "Refusing to use '$suffix' as a filename suffix."
        return 1
    fi

    zipname="local_unifiedgrader_${suffix}.zip"
    zippath="$(dirname "$DEV_DIR")/$zipname"

    if ! git -C "$DEV_DIR" rev-parse --git-dir >/dev/null 2>&1; then
        echo "Not a git repository, so there is nothing to archive. Aborting."
        return 1
    fi

    # An unclean tree means the zip will not match what is on screen.
    if [ -n "$(git -C "$DEV_DIR" status --porcelain)" ]; then
        echo ""
        echo "WARNING: uncommitted changes present. The zip is built from HEAD,"
        echo "         so those changes will NOT be in it:"
        git -C "$DEV_DIR" status --short | sed 's/^/           /'
        echo ""
        if [ "$FORCE" -eq 1 ]; then
            echo "--force given; building from HEAD regardless."
        elif [ -t 0 ]; then
            printf "Continue anyway? [y/N] "
            read -r reply
            case "$reply" in
                [yY]*) ;;
                *) echo "Aborted."; return 1 ;;
            esac
        else
            # No terminal to ask at - a prompt here would hang forever rather
            # than fail, which is the worse of the two.
            echo "Not running interactively, so refusing to guess."
            echo "Commit the changes, or re-run with --force to build from HEAD."
            return 1
        fi
    fi

    echo ""
    echo "Building $zipname (release $release) from HEAD..."
    # No rm here: git archive -o overwrites, so deleting first bought nothing
    # and put a derived path in front of rm.
    if ! git -C "$DEV_DIR" archive --format=zip -9 \
            --prefix=unifiedgrader/ -o "$zippath" HEAD; then
        echo "git archive failed. Aborting."
        return 1
    fi

    # Prove the two things Moodle's validator checks, rather than assuming them.
    local roots
    roots=$(unzip -Z1 "$zippath" | cut -d/ -f1 | sort -u)
    if [ "$roots" != "unifiedgrader" ]; then
        echo "Unexpected root director(ies) in the zip: $roots"
        echo "Moodle requires exactly one, named unifiedgrader."
        return 1
    fi
    if unzip -Z1 "$zippath" | grep -qE '^unifiedgrader/vendor/'; then
        echo "The zip contains vendor/. Check .gitattributes."
        return 1
    fi

    # tests/ ships by default - the Marketplace package should carry it. --slim
    # drops it for a production-only package; 184K, so rarely worth a second
    # artifact and the risk of uploading the wrong one.
    if [ "$SLIM" -eq 1 ]; then
        zip -q -d "$zippath" 'unifiedgrader/tests/*' >/dev/null 2>&1
        if unzip -Z1 "$zippath" | grep -qE '^unifiedgrader/tests/'; then
            echo "--slim asked for, but tests/ is still in the zip."
            return 1
        fi
        echo "  (slim: tests/ removed)"
    fi

    echo "  $zippath"
    echo "  $(unzip -Z1 "$zippath" | wc -l | tr -d ' ') files, $(du -h "$zippath" | cut -f1)"
    return 0
}

if ! verify_thirdparty_integrity; then
    exit 1
fi

# --zip-only stops here: the integrity check above still runs, because a broken
# bundled library should never reach a release either.
if [ "$DO_DEPLOY" -eq 0 ]; then
    build_release_zip || exit 1
    echo ""
    echo "Done — zip only, nothing deployed."
    exit 0
fi

# Where a given install keeps its plugins. Moodle 5.1 moved the webroot into
# public/, so plugins live at <root>/public/local/... . Older trees keep
# <root>/local/...; detected from the tree rather than configured.
plugin_dir_for() {
    if [ -f "$1/public/version.php" ]; then
        echo "$1/public/local/unifiedgrader"
    else
        echo "$1/local/unifiedgrader"
    fi
}

# Refuse to deploy anywhere if any target is missing, rather than updating some
# installs and leaving the others on stale code with a non-zero exit nobody
# reads. A missing local/unifiedgrader is fine — that is a first install.
missing=0
for dir in "${MOODLE_DIRS[@]}"; do
    if [ ! -f "$dir/config.php" ]; then
        echo "Not a Moodle install (no config.php): $dir"
        missing=$((missing + 1))
    elif [ ! -f "$dir/version.php" ] && [ ! -f "$dir/public/version.php" ]; then
        echo "No version.php in $dir or $dir/public — is this a Moodle root?"
        missing=$((missing + 1))
    fi
done
if [ "$missing" -gt 0 ]; then
    echo "Aborting deploy: fix MOODLE_DIRS at the top of this script."
    exit 1
fi

# Pass 1 — the build host. Sync without amd/build (grunt is about to write it),
# compile, then copy the result back to the dev folder so the remaining installs
# and the repo get the same artifacts.
BUILD_DIR="${MOODLE_DIRS[0]}"
BUILD_PLUGIN_DIR="$(plugin_dir_for "$BUILD_DIR")"

echo ""
echo "Syncing $DEV_DIR → $BUILD_PLUGIN_DIR (build host)"
mkdir -p "$BUILD_PLUGIN_DIR"
rsync -av --delete \
  --exclude='.claude' \
  --exclude='amd/build' \
  --exclude='.eslintrc' \
  --exclude='deploy.sh' \
  --exclude='.git' \
  --exclude='vendor' \
  "$DEV_DIR/" "$BUILD_PLUGIN_DIR/"

echo ""
# Grunt runs from the Moodle root, where Gruntfile.js stays, and takes the
# plugin's path relative to it: public/local/unifiedgrader from 5.1.
BUILD_GRUNT_ROOT="${BUILD_PLUGIN_DIR#"$BUILD_DIR"/}"

echo "Building AMD modules in $BUILD_DIR ($BUILD_GRUNT_ROOT)..."
if ! (cd "$BUILD_DIR" && npx grunt amd --root="$BUILD_GRUNT_ROOT"); then
    echo "AMD build failed. Aborting before the other installs are touched."
    exit 1
fi

echo ""
echo "Copying built files back to dev folder..."
cp -R "$BUILD_PLUGIN_DIR/amd/build" "$DEV_DIR/amd/"

# Pass 2 — every other install. amd/build is no longer excluded: it now exists
# in the dev folder and is the artifact we want shipped, unbuilt and unchanged.
for dir in "${MOODLE_DIRS[@]:1}"; do
    target="$(plugin_dir_for "$dir")"
    echo ""
    echo "Syncing $DEV_DIR → $target"
    mkdir -p "$target"
    rsync -av --delete \
      --exclude='.claude' \
      --exclude='.eslintrc' \
      --exclude='deploy.sh' \
      --exclude='.git' \
      --exclude='vendor' \
      "$DEV_DIR/" "$target/"
done

echo ""
echo "Purging Moodle caches..."
for dir in "${MOODLE_DIRS[@]}"; do
    # The CLI scripts stay at <root>/admin/cli even where the webroot moved
    # into public/.
    cli="$dir/admin/cli/purge_caches.php"
    echo "  $dir"
    php "$cli"
done

echo ""
echo "Done — deployed to ${#MOODLE_DIRS[@]} install(s)."

if [ "$DO_ZIP" -eq 1 ]; then
    build_release_zip || exit 1
fi
