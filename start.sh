#!/bin/sh
set -eu
cd "$(dirname "$0")"
exec ./php.sh artisan serve --host=127.0.0.1 --port="${PORT:-8006}"
