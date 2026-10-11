#!/bin/sh
set -eu
cd "$(dirname "$0")"
if [ -x .tools/php ]; then
  export PATH="$PWD/.tools:$PATH"
  exec .tools/php "$@"
fi
exec php "$@"
