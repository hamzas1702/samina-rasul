#!/usr/bin/env bash
#
# HTTP helpers for the deploy workflow.
#
# Why this exists: every call site used to be written as
#
#   HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$URL")
#
# under a `bash -e` step. An assignment carries the exit status of its command
# substitution, so any transport-level curl failure killed the step outright,
# before the code that was supposed to interpret the status ever ran. The
# symptom is a bare "Error: Process completed with exit code 92" with no
# indication of what happened - 92 being CURLE_HTTP2_STREAM, an HTTP/2 framing
# error, which is exactly the sort of thing a deploy should retry rather than
# die on. In the health check it was worse: the three-attempt retry loop could
# never reach attempt two, because attempt one took the whole step down.
#
# sr_http() never returns non-zero. It prints the HTTP status on stdout - 000
# when the request did not complete - and explains any curl failure on stderr.
# Deciding what a status means is the caller's job, which is the point.

# HTTP/1.1 is forced. The only thing these requests do is fetch a small page or
# poke an endpoint, HTTP/2 buys nothing here, and it is where exit 92 came from:
# LiteSpeed in front of the site resets the stream on a request that ends with
# an abrupt exit() from PHP.
SR_CURL_BASE=(--silent --show-error --http1.1 --location --max-time 20 --connect-timeout 10)

# Every caller builds URLs as "$LIVE_URL/path". A trailing slash in the secret
# therefore yields "https://site//path", which some WAFs and LiteSpeed setups
# reject outright - a whole class of unexplained deploy failure caused by one
# character in a settings field nobody looks at. Normalised here, once, so it is
# fixed for every step that sources this file.
if [ -n "${LIVE_URL:-}" ]; then
	LIVE_URL="${LIVE_URL%/}"
	export LIVE_URL
fi

# Chrome UA: some managed hosts serve a challenge page to unrecognised agents,
# which fails the build-marker check for reasons that have nothing to do with
# the release.
SR_UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

# curl exit codes worth naming in a deploy log.
sr_curl_error_name() {
	case "$1" in
		6)  echo "could not resolve host" ;;
		7)  echo "could not connect" ;;
		28) echo "timed out" ;;
		35) echo "TLS handshake failed" ;;
		52) echo "empty reply from server" ;;
		56) echo "connection reset while receiving" ;;
		92) echo "HTTP/2 stream error" ;;
		*)  echo "curl error $1" ;;
	esac
}

# sr_http <body_file|/dev/null> <url> [extra curl args...]
#
# Prints the HTTP status code, or 000 if the request did not complete.
# Always returns 0.
sr_http() {
	local body="$1"
	local url="$2"
	shift 2

	local code=""
	local rc=0

	# `|| rc=$?`, not a bare assignment followed by `rc=$?`. Under `set -e` a
	# failing command substitution in a plain assignment aborts the shell on the
	# spot - the next line never runs - so the function has to put the assignment
	# inside a || list to survive being called as a statement rather than from
	# inside $( ). Being safe only when called one particular way is not safe.
	code=$(curl "${SR_CURL_BASE[@]}" -A "$SR_UA" -o "$body" -w "%{http_code}" "$@" "$url" 2>/tmp/sr_curl_err) || rc=$?

	if [ "$rc" -ne 0 ]; then
		echo "  curl failed: $(sr_curl_error_name "$rc")" >&2
		if [ -s /tmp/sr_curl_err ]; then
			sed 's/^/  /' /tmp/sr_curl_err >&2
		fi
		echo "000"
		return 0
	fi

	echo "${code:-000}"
	return 0
}

# sr_http_retry <attempts> <body_file> <url> [extra curl args...]
#
# Retries on anything that is not a 2xx/3xx, with a short backoff, because a
# host that has just had its OPcache flushed can serve one 500 while it warms.
# Prints the status of the final attempt.
sr_http_retry() {
	local attempts="$1"
	local body="$2"
	local url="$3"
	shift 3

	local i code
	for i in $(seq 1 "$attempts"); do
		code=$(sr_http "$body" "$url" "$@")
		case "$code" in
			2*|3*) echo "$code"; return 0 ;;
		esac
		if [ "$i" -lt "$attempts" ]; then
			echo "  attempt $i returned $code, retrying in $((i * 3))s..." >&2
			sleep $((i * 3))
		fi
	done

	echo "$code"
	return 0
}
