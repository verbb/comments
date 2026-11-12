#!/bin/sh
set -eu
find src tests/mcp -name '*.php' -type f > /tmp/comments-php-files
while IFS= read -r file; do
    php -l "$file" > /dev/null
done < /tmp/comments-php-files
printf '%s\n' 'PHP syntax checks passed.'
php tests/mcp/protocol.php
php tests/mcp/access.php
php tests/mcp/http.php
php tests/mcp/actor.php
