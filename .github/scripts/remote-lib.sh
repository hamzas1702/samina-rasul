#!/usr/bin/env bash
#
# Functions that run ON THE SERVER.
#
# Not uploaded: each deploy step pipes this file plus its own script into
# `ssh … bash -s`, so the shared parts live in one place and nothing is left
# behind on the host. Every step needs the same two answers - where WordPress
# is, and how to talk to it - and having three copies of that is how one copy
# gets fixed and the others do not.
#
# Nothing here uses `set -e`. These run on the far side of an ssh pipe where a
# non-zero exit is the only signal the caller gets, so each function returns a
# value the caller checks rather than aborting the session.

# ---------------------------------------------------------------------------
# Where is WordPress?
#
# REMOTE_PATH is the directory holding wp-content - the rsync targets prove that
# much - but on this host it is not where wp-config.php lives, which is why
# `wp cache flush` reported "This does not seem to be a WordPress installation"
# on every deploy for months while `|| true` swallowed it.
#
# Echoes the path, or nothing when there is no install to be found.
# ---------------------------------------------------------------------------
sr_find_wp_root() {
	local candidate

	for candidate in \
		"$REMOTE_PATH" \
		"$REMOTE_PATH/public_html" \
		"$(dirname "$REMOTE_PATH")" \
		"$(dirname "$REMOTE_PATH")/public_html"
	do
		[ -n "$candidate" ] && [ -d "$candidate" ] || continue
		if wp core is-installed --path="$candidate" --skip-plugins --skip-themes >/dev/null 2>&1; then
			printf '%s' "$candidate"
			return 0
		fi
	done

	return 1
}

# ---------------------------------------------------------------------------
# The hostname WordPress believes it is serving.
#
# Read from the database rather than from the LIVE_URL secret: it is the value
# WordPress will actually compare the Host header against, so it cannot drift
# from whatever someone typed into a settings field. Falls back to LIVE_URL when
# wp-cli is unavailable.
# ---------------------------------------------------------------------------
sr_site_host() {
	local wp_path="$1"
	local url=""

	if [ -n "$wp_path" ]; then
		url=$(wp option get home --path="$wp_path" --skip-plugins --skip-themes 2>/dev/null)
	fi

	[ -n "$url" ] || url="${LIVE_URL:-}"

	printf '%s' "$url" | sed -E 's#^https?://##; s#/.*$##'
}

# ---------------------------------------------------------------------------
# Fetch a path from this server, over the loopback.
#
# The reason everything moved in here: requests from the GitHub runner to the
# public URL are reset by the host - `curl (56) Recv failure` on a plain GET of
# the homepage, every attempt, and `curl (92)` before that over HTTP/2. Shared
# hosting commonly refuses datacenter IP ranges, and no amount of retrying or
# user-agent spoofing changes that. From inside the server there is no edge, no
# WAF and no IP reputation involved.
#
# --resolve, not a Host header. Both put the right name in the request, but a
# Host header on an http:// URL leaves WordPress seeing a plain-HTTP request for
# an https:// site, so it answers 301 to the canonical URL - which is what the
# first version of this did, on every attempt, until the check gave up.
#
# --resolve instead requests the real https:// URL and pins that hostname to the
# loopback address. WordPress sees exactly the request a browser makes, TLS gets
# the right SNI, and no redirect happens. Both ports are pinned so that if
# something does redirect http->https the second hop also stays on loopback and
# never escapes to public DNS.
#
# Usage: sr_loopback_fetch <body_file> <path> <host> [extra curl args...]
# Echoes the HTTP status, or 000. Always returns 0.
# ---------------------------------------------------------------------------
sr_loopback_fetch() {
	local body="$1"
	local path="$2"
	local host="$3"
	shift 3

	local ip scheme status alt

	# A canonical-redirect rule (www -> bare, or the reverse) would otherwise
	# send the second hop to public DNS, straight back out through the edge this
	# function exists to bypass. Pinning both spellings keeps every hop local.
	case "$host" in
		www.*) alt="${host#www.}" ;;
		*)     alt="www.$host" ;;
	esac

	# Which loopback address and scheme the web server listens on varies by
	# host: an IPv6-only bind is indistinguishable from a broken endpoint if
	# only one is tried. https first, because that is what a live site is.
	for scheme in https http; do
		for ip in "127.0.0.1" "[::1]"; do
			status=$(curl --silent --show-error --http1.1 --insecure --location \
				--max-redirs 3 --max-time 20 --connect-timeout 5 \
				--resolve "$host:443:$ip" \
				--resolve "$host:80:$ip" \
				--resolve "$alt:443:$ip" \
				--resolve "$alt:80:$ip" \
				-o "$body" -w "%{http_code}" \
				"$@" \
				"${scheme}://${host}${path}" 2>/dev/null) || status="000"

			# 000 means nothing is listening on that address/scheme. Anything
			# else reached PHP and is the answer, right or wrong.
			if [ "$status" != "000" ]; then
				printf '%s' "$status"
				return 0
			fi
		done
	done

	printf '000'
	return 0
}
