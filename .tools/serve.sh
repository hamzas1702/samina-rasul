#!/bin/zsh
DIR="$(cd "$(dirname "$0")" && pwd)"
export PHP_CLI_SERVER_WORKERS=6
exec "$DIR/php" -d memory_limit=512M -S localhost:8787 -t "$DIR/../site" "$DIR/../site/router.php"
