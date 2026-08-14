#!/usr/bin/env bash
#
# Health check, run ON THE SERVER. This is the gate the deploy stands or falls
# on, so it is worth being precise about what it proves and what it does not.
#
# Proves: PHP renders the pages without fatalling, and the HTML carries the
# build marker for this exact commit - so the files that were just swapped in
# are the files being executed, and neither OPcache nor a page cache is serving
# the previous release.
#
# Does not prove: the site is reachable from the public internet. It cannot,
# from in here. The public check that follows on the runner covers that as far
# as it is able, and is informational because this host resets connections from
# GitHub's IP ranges whether the site is healthy or not.
#
# Expects: REMOTE_PATH, GITHUB_SHA, optionally HEALTH_CHECK_PRODUCT_PATH.
# Requires remote-lib.sh to have been piped in ahead of it.

WP_PATH=$(sr_find_wp_root)

if [ -z "$WP_PATH" ]; then
	echo "FAIL: no WordPress install found at or near $REMOTE_PATH."
	echo "      The health check cannot verify anything. Point the REMOTE_PATH secret"
	echo "      at the directory holding wp-config.php."
	exit 1
fi

HOST=$(sr_site_host "$WP_PATH")

if [ -z "$HOST" ]; then
	echo "FAIL: could not determine the site's hostname from WordPress or LIVE_URL."
	exit 1
fi

echo "Checking from the server (WordPress at $WP_PATH, Host: $HOST)."

# Home and cart always exist. A product page exercises far more theme code, but
# pinning one product slug means the day the client deletes that sample every
# deploy fails and rollback fires on a good release - so it is opt-in.
PATHS=("/" "/cart/")
if [ -n "${HEALTH_CHECK_PRODUCT_PATH:-}" ]; then
	PATHS+=("$HEALTH_CHECK_PRODUCT_PATH")
fi

BUST="?sr_qa=$GITHUB_SHA"
FAILED=0

for path in "${PATHS[@]}"; do
	PASSED=0

	for attempt in 1 2 3; do
		echo "Attempt $attempt: $path$BUST"

		STATUS=$(sr_loopback_fetch /tmp/sr_health_body.txt "$path$BUST" "$HOST")

		if [ "$STATUS" = "200" ] && grep -q "sr_build_marker: $GITHUB_SHA" /tmp/sr_health_body.txt; then
			echo "  OK: 200, and the build marker matches this commit."
			PASSED=1
			break
		fi

		if [ "$STATUS" = "200" ]; then
			echo "  200, but the build marker for this commit is missing."
			echo "     Either the swap did not land, or OPcache is still serving the previous release."
		else
			echo "  HTTP $STATUS."
		fi

		if [ -s /tmp/sr_health_body.txt ]; then
			echo "     Body tail:"
			tail -n 10 /tmp/sr_health_body.txt | sed 's/^/     /'
		fi

		[ "$attempt" -lt 3 ] && { echo "     Retrying in $((attempt * 3))s..."; sleep $((attempt * 3)); }
	done

	if [ "$PASSED" -ne 1 ]; then
		echo "FAIL: $path did not pass after 3 attempts."
		FAILED=1
		break
	fi
done

[ "$FAILED" -eq 0 ] && echo "All health checks passed on the server."

exit "$FAILED"
