#!/usr/bin/env bash
#
# Builds and starts both apps, installs their dependencies and creates their databases.
# Safe to run again: it rebuilds the databases from scratch (dev and test).
#
#   ./setup.sh
#   ./stage-check.sh      # then check the whole stage story

set -euo pipefail
cd "$(dirname "$0")"

docker compose up -d --build

for app in shop gateway; do
    echo "== $app"
    # vendor/ lives in the mounted project folder, so it is installed inside the container
    docker compose exec -T "$app" composer install --no-interaction --quiet
    docker compose exec -T "$app" ./gluo --env=dev migrate:reset --yes
    docker compose exec -T "$app" ./gluo --env=test migrate:reset --yes
done

rm -f shop/var/log/*.log
touch shop/var/log/payments.log

echo
echo "Shop:     http://localhost:8080   (webhook: POST /webhook)"
echo "Gateway:  http://localhost:8081/operator"
echo "Log:      tail -f shop/var/log/payments.log"
