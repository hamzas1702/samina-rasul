#!/usr/bin/env bash
#
# Preflight, run ON THE SERVER before anything is copied or moved.
#
# The one thing worth proving up front: REMOTE_PATH is the WordPress root.
#
# Every rsync in this workflow writes to "$REMOTE_PATH/wp-content/…". If
# REMOTE_PATH is not the directory WordPress actually runs from, rsync does not
# complain - it creates the directories and copies the theme into a folder
# nothing serves. The swap then succeeds, the backups rotate, and the deploy
# reports success while the live site never changes. That failure is silent and
# expensive, and it is entirely preventable with one check before the first
# byte moves.
#
# Expects: REMOTE_PATH. Requires remote-lib.sh piped in ahead of it.

if [ ! -d "$REMOTE_PATH" ]; then
	echo "FAIL: REMOTE_PATH does not exist on the server."
	echo "      REMOTE_PATH = $REMOTE_PATH"
	exit 1
fi

# wp-cli searches upward for wp-load.php, so this passing means REMOTE_PATH is
# the root or lives inside one.
if wp core is-installed --path="$REMOTE_PATH" --skip-plugins --skip-themes >/dev/null 2>&1 \
	&& [ -d "$REMOTE_PATH/wp-content" ]; then

	SITE_HOST=$(sr_site_host "$REMOTE_PATH")
	echo "Preflight OK."
	echo "  WordPress root : $REMOTE_PATH"
	echo "  Site host      : ${SITE_HOST:-unknown}"

	# Not fatal - the loader is copied later in this same run - but if it is
	# missing now on a repeat deploy, something removed it.
	[ -f "$REMOTE_PATH/wp-content/mu-plugins/samina-core.php" ] \
		|| echo "  Note: samina-core.php is not present yet; this deploy will install it."

	exit 0
fi

# ---------------------------------------------------------------------------
# Not a WordPress root. Rather than just refusing, go and find the real one -
# the operator is being asked to fix a secret they cannot see the effect of, so
# handing them the exact value to paste is the difference between a two-minute
# fix and another round of guessing.
#
# Hostinger's layout is ~/domains/<domain>/public_html, with ~/public_html
# usually a symlink to whichever domain is primary. A preview domain that has
# been replaced leaves its old directory behind, which is the trap here.
# ---------------------------------------------------------------------------
echo "FAIL: REMOTE_PATH is not a WordPress installation."
echo "      REMOTE_PATH = $REMOTE_PATH"
echo ""
echo "      Nothing has been copied or changed. Every rsync in this workflow writes to"
echo "      \$REMOTE_PATH/wp-content/, so with this value the theme would be installed"
echo "      into a directory nothing serves and the deploy would report success."
echo ""

FOUND=0
for candidate in "$HOME"/domains/*/public_html "$HOME/public_html" "$REMOTE_PATH"/*/public_html; do
	[ -d "$candidate" ] || continue
	if wp core is-installed --path="$candidate" --skip-plugins --skip-themes >/dev/null 2>&1; then
		if [ "$FOUND" -eq 0 ]; then
			echo "      WordPress installations found on this server:"
			FOUND=1
		fi
		HOST=$(sr_site_host "$candidate")
		# resolve symlinks so two paths to the same site are recognisable
		REAL=$(cd "$candidate" 2>/dev/null && pwd -P)
		printf '        %s\n' "$candidate"
		printf '          site: %s\n' "${HOST:-unknown}"
		[ "$REAL" != "$candidate" ] && printf '          (real path: %s)\n' "$REAL"
	fi
done

if [ "$FOUND" -eq 0 ]; then
	echo "      No WordPress installation was found under $HOME either."
	echo "      Check the SSH_USER secret - this may be the wrong account."
else
	echo ""
	echo "      Set the REMOTE_PATH secret to the path whose site matches your domain."
fi

exit 1
