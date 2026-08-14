#!/usr/bin/env bash
#
# Cache work, run ON THE SERVER. Best effort throughout: this script never fails
# the deploy.
#
# The gate is remote-health.sh, which asserts this commit's build marker is in
# the rendered HTML. That is a direct test of "the new code is being served", so
# a stale OPcache or page cache fails it and triggers rollback on its own. A
# second hard gate here would only add a way for a good release to be rejected.
#
# Expects: REMOTE_PATH, LIVE_URL, SR_OPCACHE_SECRET.
# Requires remote-lib.sh to have been piped in ahead of it.

WP_PATH=$(sr_find_wp_root)

if [ -z "$WP_PATH" ]; then
	echo "WARNING: no WordPress install found at or near $REMOTE_PATH - caches were NOT flushed."
	echo "         Point the REMOTE_PATH secret at the directory holding wp-config.php."
else
	[ "$WP_PATH" = "$REMOTE_PATH" ] || echo "Note: WordPress root is $WP_PATH, not REMOTE_PATH ($REMOTE_PATH)."

	# -----------------------------------------------------------------------
	# Does the LIVE_URL secret still match the site?
	#
	# Worth its own warning because the failure it causes looks like anything
	# but a configuration mistake: every request from the runner is reset
	# mid-response, which reads as a firewall, a WAF, or a dead server. It is
	# none of those - the host simply does not answer to a hostname it no
	# longer serves. That is what happens to a *.hostingersite.com preview
	# domain once a real domain is attached.
	# -----------------------------------------------------------------------
	SITE_HOST=$(sr_site_host "$WP_PATH")
	SECRET_HOST=$(printf '%s' "${LIVE_URL:-}" | sed -E 's#^https?://##; s#/.*$##')

	if [ -n "$SECRET_HOST" ] && [ -n "$SITE_HOST" ] && [ "$SECRET_HOST" != "$SITE_HOST" ]; then
		echo ""
		echo "WARNING: the LIVE_URL secret does not match this site."
		echo "         LIVE_URL secret : $SECRET_HOST"
		echo "         WordPress home  : $SITE_HOST"
		echo "         Update the LIVE_URL repository secret to https://$SITE_HOST."
		echo "         Until then the public reachability check cannot succeed - it is"
		echo "         asking the server for a hostname it does not serve."
		echo ""
	fi

	if wp cache flush --path="$WP_PATH" --skip-plugins --skip-themes >/dev/null 2>&1; then
		echo "Object cache flushed."
	else
		echo "WARNING: object cache flush failed."
	fi

	# litespeed-purge only exists when the LiteSpeed Cache plugin is active.
	# Probed, not called blind - "is not a registered wp command" in a deploy
	# log trains everyone to ignore deploy logs.
	if wp litespeed-purge --path="$WP_PATH" --help >/dev/null 2>&1; then
		wp litespeed-purge all --path="$WP_PATH" --quiet \
			&& echo "LiteSpeed cache purged." \
			|| echo "WARNING: LiteSpeed purge failed."
	else
		echo "LiteSpeed Cache not active - no page cache to purge."
	fi
fi

# ---------------------------------------------------------------------------
# OPcache.
#
# The web SAPI's OPcache is a different cache from the CLI's, so wp-cli cannot
# clear it - it has to be an HTTP request, which is what the endpoint in
# samina-core/opcache-reset.php exists for. Over the loopback, because requests
# from the GitHub runner to the public URL are reset by this host.
# ---------------------------------------------------------------------------
if [ -z "${SR_OPCACHE_SECRET:-}" ]; then
	echo "WARNING: SR_OPCACHE_SECRET is not set - skipping the OPcache reset."
	echo "         WordPress will only pick up the new files if opcache.validate_timestamps is On."
	echo "         Define it in $WP_PATH/wp-config.php, matching the GitHub secret of that name."
	exit 0
fi

if ! command -v curl >/dev/null 2>&1; then
	echo "WARNING: curl is not available on the server - skipping the OPcache reset."
	exit 0
fi

HOST=$(sr_site_host "$WP_PATH")
echo "Resetting OPcache over the loopback (Host: $HOST)..."

STATUS=$(sr_loopback_fetch /tmp/sr_opcache_out "/?sr_opcache_reset=1" "$HOST" \
	-X POST -H "X-SR-Deploy-Token: $SR_OPCACHE_SECRET")

case "$STATUS" in
	200)
		echo "OPcache reset."
		;;
	403)
		echo "WARNING: 403 - the token did not match."
		echo "         SR_OPCACHE_SECRET in $WP_PATH/wp-config.php must equal the GitHub secret of that name."
		;;
	404)
		echo "WARNING: 404 - the endpoint is not loading."
		echo "         Check that $REMOTE_PATH/wp-content/mu-plugins/samina-core.php exists."
		;;
	000)
		echo "WARNING: no loopback address answered - the web server may not listen on localhost here."
		;;
	*)
		echo "WARNING: OPcache reset returned $STATUS."
		;;
esac

exit 0
