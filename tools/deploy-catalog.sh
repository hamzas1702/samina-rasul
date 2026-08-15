#!/usr/bin/env bash
#
# Push the catalogue to the live store.
#
# The GitHub deploy ships code only - it rsyncs the theme and the samina-core
# mu-plugin and nothing else, and .gitignore keeps the database and uploads/ out
# of the repository entirely. Products, variations, add-ons and photographs
# therefore never travel with a release: they live in the database, and the
# database on the server is the only copy of the live one.
#
# So the catalogue is built on the server, from the same CSV and the same
# scripts that built it locally. Same input, same code, same result.
#
# Usage:
#   SSH_HOST=… SSH_USER=… SSH_PORT=22 REMOTE_PATH=/home/…/public_html \
#     tools/deploy-catalog.sh [--dry-run]
#
# Every stage is keyed on SKU and re-runnable, and nothing here deletes a
# product. Run it as often as the catalogue changes.

set -euo pipefail

DRY_RUN=0
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CSV="$ROOT/catalog/samina-rasul-product-catalog.csv"
IMAGES="$ROOT/catalog/images"

# ---------------------------------------------------------------------------
# Preflight. Every one of these is cheap and every one of them has a failure
# mode that is expensive to diagnose after the fact.
# ---------------------------------------------------------------------------

for var in SSH_HOST SSH_USER REMOTE_PATH; do
	if [ -z "${!var:-}" ]; then
		echo "FAIL: \$$var is not set." >&2
		echo "  SSH_HOST=… SSH_USER=… REMOTE_PATH=… tools/deploy-catalog.sh" >&2
		exit 1
	fi
done

SSH_PORT="${SSH_PORT:-22}"
SSH_CMD=(ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -p "$SSH_PORT" "$SSH_USER@$SSH_HOST")
REMOTE_PATH="${REMOTE_PATH%/}"

[ -f "$CSV" ] || { echo "FAIL: no catalogue at $CSV. Run parse-raw-catalog.php first." >&2; exit 1; }

echo "==> Checking the server"

# WordPress root, not just any directory: rsync would happily create
# wp-content/uploads/catalog inside a directory that is not an install, and
# every later step would pass while the live site never changed.
"${SSH_CMD[@]}" "[ -f '$REMOTE_PATH/wp-load.php' ]" \
	|| { echo "FAIL: $REMOTE_PATH is not a WordPress root (no wp-load.php)." >&2; exit 1; }

if ! "${SSH_CMD[@]}" "cd '$REMOTE_PATH' && wp --skip-plugins --skip-themes core is-installed" >/dev/null 2>&1; then
	echo "FAIL: WP-CLI is not usable on the server." >&2
	echo "  Check with: ssh -p $SSH_PORT $SSH_USER@$SSH_HOST 'cd $REMOTE_PATH && wp --info'" >&2
	echo "  Without it, use WooCommerce → Products → Import in wp-admin instead." >&2
	exit 1
fi

REMOTE_TMP="$REMOTE_PATH/.sr-catalog-$(date +%s)"

if [ "$DRY_RUN" = "1" ]; then
	echo "dry run: would sync $(ls -1 "$IMAGES" 2>/dev/null | wc -l | tr -d ' ') images and import $(( $(wc -l < "$CSV") - 1 )) CSV lines"
	exit 0
fi

# ---------------------------------------------------------------------------
# Photographs
#
# Into uploads/, because WordPress can only serve and resize a file that lives
# there. --size-only: these are finished exports that never change in place, and
# comparing 23 MB by checksum on every run buys nothing.
# ---------------------------------------------------------------------------

if [ -d "$IMAGES" ]; then
	echo "==> Syncing photographs"
	rsync -az --size-only \
		--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
		-e "ssh -o StrictHostKeyChecking=accept-new -p $SSH_PORT" \
		"$IMAGES/" "$SSH_USER@$SSH_HOST:$REMOTE_PATH/wp-content/uploads/catalog/"
else
	echo "==> No catalog/images directory - skipping photographs"
fi

# ---------------------------------------------------------------------------
# Scripts and catalogue
#
# Into a timestamped directory that is removed at the end, so nothing
# executable is left behind under the docroot.
# ---------------------------------------------------------------------------

echo "==> Uploading the importer"
"${SSH_CMD[@]}" "mkdir -p '$REMOTE_TMP/catalog' '$REMOTE_TMP/tools/seed'"

# Removed however this exits - a failed import must not leave PHP that writes
# products sitting under the docroot. A function, because an array cannot be
# expanded from inside the single-quoted trap string.
sr_cleanup() {
	"${SSH_CMD[@]}" "rm -rf '$REMOTE_TMP'" >/dev/null 2>&1 || true
}
trap sr_cleanup EXIT

scp -q -o StrictHostKeyChecking=accept-new -P "$SSH_PORT" \
	"$ROOT/tools/seed/seed-lib.php" \
	"$ROOT/tools/seed/seed-1-taxonomies.php" \
	"$ROOT/tools/seed/seed-5-catalog.php" \
	"$ROOT/tools/seed/seed-4-specs.php" \
	"$SSH_USER@$SSH_HOST:$REMOTE_TMP/tools/seed/"

scp -q -o StrictHostKeyChecking=accept-new -P "$SSH_PORT" \
	"$CSV" "$SSH_USER@$SSH_HOST:$REMOTE_TMP/catalog/"

# The description overrides, when there are any. They are read relative to the
# catalogue root, so they have to sit beside it.
if compgen -G "$ROOT/catalog/descriptions/*" >/dev/null; then
	"${SSH_CMD[@]}" "mkdir -p '$REMOTE_TMP/catalog/descriptions'"
	scp -q -o StrictHostKeyChecking=accept-new -P "$SSH_PORT" \
		"$ROOT"/catalog/descriptions/* "$SSH_USER@$SSH_HOST:$REMOTE_TMP/catalog/descriptions/"
fi

# ---------------------------------------------------------------------------
# Import
#
# Order matters: taxonomies and attributes have to exist before a variation can
# reference them, and the spec attributes are written last because they read the
# products the importer creates.
# ---------------------------------------------------------------------------

echo "==> Importing"
"${SSH_CMD[@]}" "cd '$REMOTE_PATH' \
	&& wp eval-file '$REMOTE_TMP/tools/seed/seed-1-taxonomies.php' \
	&& wp eval-file '$REMOTE_TMP/tools/seed/seed-5-catalog.php' \
	&& wp eval-file '$REMOTE_TMP/tools/seed/seed-4-specs.php'"

echo "==> Fetching the reports"
scp -q -o StrictHostKeyChecking=accept-new -P "$SSH_PORT" \
	"$SSH_USER@$SSH_HOST:$REMOTE_TMP/catalog/*-report.md" "$ROOT/catalog/" 2>/dev/null \
	|| echo "  (no reports came back - check the output above)"

echo "==> Flushing caches"
"${SSH_CMD[@]}" "cd '$REMOTE_PATH' && wp cache flush && wp transient delete --all" >/dev/null 2>&1 || true

echo
echo "Done. The photographs stay in wp-content/uploads/catalog/ - the media"
echo "library points at them. Everything else was removed from the server."
