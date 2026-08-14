#!/usr/bin/env bash
#
# Rollback, run ON THE SERVER: restore the previous release, clear the caches,
# and verify the rolled-back site actually serves.
#
# Exit codes are the only channel back to the workflow:
#   0  rolled back and verified
#   10 nothing to roll back to (normal on a first deploy - not a failure)
#   1  rollback attempted and failed
#
# Expects: REMOTE_PATH, GITHUB_SHA, LIVE_URL, SR_OPCACHE_SECRET,
#          optionally HEALTH_CHECK_PRODUCT_PATH.
# Requires remote-lib.sh to have been piped in ahead of it.

THEME_DIR="$REMOTE_PATH/wp-content/themes/samina-rasul"
MU_DIR="$REMOTE_PATH/wp-content/mu-plugins/samina-core"

# `|| true`: an unmatched glob makes GNU ls exit 2, and an assignment carries
# its command substitution's status. That is the bug that took down the swap
# step on the very first deploy.
LATEST_THEME_BACKUP=$(ls -1dt "$THEME_DIR-backups"/samina-rasul-* 2>/dev/null | head -n 1 || true)
LATEST_MU_BACKUP=$(ls -1dt "$MU_DIR-backups"/samina-core-* 2>/dev/null | head -n 1 || true)

if [ -z "$LATEST_THEME_BACKUP" ] && [ -z "$LATEST_MU_BACKUP" ]; then
	echo "No previous release on disk - nothing to roll back to."
	echo "On a first deploy this is expected: what was just installed is all there is."
	exit 10
fi

if [ -n "$LATEST_THEME_BACKUP" ]; then
	echo "Restoring theme from $LATEST_THEME_BACKUP"
	rm -rf "$THEME_DIR" && mv "$LATEST_THEME_BACKUP" "$THEME_DIR" || exit 1
fi

if [ -n "$LATEST_MU_BACKUP" ]; then
	echo "Restoring mu-plugin from $LATEST_MU_BACKUP"
	rm -rf "$MU_DIR" && mv "$LATEST_MU_BACKUP" "$MU_DIR" || exit 1
fi

WP_PATH=$(sr_find_wp_root)
HOST=$(sr_site_host "$WP_PATH")

if [ -n "$WP_PATH" ]; then
	wp cache flush --path="$WP_PATH" --skip-plugins --skip-themes >/dev/null 2>&1 || true
	if wp litespeed-purge --path="$WP_PATH" --help >/dev/null 2>&1; then
		wp litespeed-purge all --path="$WP_PATH" --quiet || true
	fi
fi

# Getting the withdrawn release out of OPcache matters more here than it does on
# the way forward, but it still does not decide the verdict - the verification
# below does.
if [ -n "${SR_OPCACHE_SECRET:-}" ] && command -v curl >/dev/null 2>&1; then
	OPC=$(sr_loopback_fetch /dev/null "/?sr_opcache_reset=1" "$HOST" \
		-X POST -H "X-SR-Deploy-Token: $SR_OPCACHE_SECRET")
	[ "$OPC" = "200" ] && echo "OPcache reset." || echo "WARNING: OPcache reset returned $OPC."
fi

sleep 3

# A rollback worked when the pages serve AND the marker of the release being
# withdrawn is gone.
PATHS=("/" "/cart/")
if [ -n "${HEALTH_CHECK_PRODUCT_PATH:-}" ]; then
	PATHS+=("$HEALTH_CHECK_PRODUCT_PATH")
fi

BUST="?sr_qa=rollback_verify_$GITHUB_SHA"

for path in "${PATHS[@]}"; do
	echo "Verifying rollback: $path"
	STATUS=$(sr_loopback_fetch /tmp/sr_rollback_body.txt "$path$BUST" "$HOST")

	if [ "$STATUS" != "200" ]; then
		echo "FAIL: $path returned HTTP $STATUS after rollback."
		[ -s /tmp/sr_rollback_body.txt ] && tail -n 10 /tmp/sr_rollback_body.txt | sed 's/^/      /'
		exit 1
	fi

	if grep -q "sr_build_marker: $GITHUB_SHA" /tmp/sr_rollback_body.txt; then
		echo "FAIL: $path still carries the build marker of the release being rolled back."
		exit 1
	fi
done

echo "Rollback verified."
exit 0
