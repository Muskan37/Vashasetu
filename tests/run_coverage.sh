#!/usr/bin/env bash
# Runs every functional suite with code coverage switched on and merges the result.
# Linux/Apache test server. On XAMPP see tests/phpunit/coverage/prepend.php for the php.ini lines.
#   APP=/var/www/html/vashasetu BASE=http://127.0.0.1/vashasetu bash tests/run_coverage.sh
set -u
APP=${APP:-/var/www/html/vashasetu}; export BASE=${BASE:-http://127.0.0.1/vashasetu}
SRC=$(cd "$(dirname "$0")/.." && pwd)
RAW=$APP/tests/results/coverage-raw
rm -rf "$RAW" "$APP/tests/results/coverage"; mkdir -p "$RAW" "$APP/tests/results/coverage"; touch "$RAW/ON"; chown -R www-data "$APP/tests/results" 2>/dev/null
cd "$APP"
php -d pcov.enabled=1 -d pcov.directory="$APP" "$(command -v phpunit)" --coverage-php "$RAW/phpunit.cov" \
    --log-junit "$APP/tests/results/phpunit-junit.xml" --testdox-text "$APP/tests/results/phpunit-testdox.txt"
cd "$SRC"
for s in api_tests system_tests ui_tests security_tests reliability_tests a11y_perf_tests; do python3 tests/$s.py > "$APP/tests/results/cov_$s.log" 2>&1; tail -1 "$APP/tests/results/cov_$s.log"; done
rm -f "$RAW/ON"
php -d memory_limit=4G "$APP/tests/phpunit/coverage/merge.php" | tail -40
