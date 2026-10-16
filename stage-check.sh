#!/usr/bin/env bash
#
# Runs the whole stage story against the running containers and checks every step in the
# shop's payments log. Run it before the talk: if it ends with "ALL GOOD", the demo works.
#
#   docker compose up -d
#   ./stage-check.sh
#
# It RESETS both databases and the payments log, so payment N and gateway transaction N
# are the same number throughout.

set -euo pipefail
cd "$(dirname "$0")"

LOG=shop/var/log/payments.log
GATEWAY=http://localhost:8081
failures=0

shop()    { docker compose exec -T shop ./gluo "$@"; }
press()   { curl -s -o /dev/null -X POST "$GATEWAY/transactions/$1/$2"; }   # what a button on /operator does
step()    { printf '\n\033[1m== %s\033[0m\n' "$1"; }
expect()  {
    if grep -qF -- "$1" "$LOG"; then
        printf '   \033[32mok\033[0m   %s\n' "$1"
    else
        printf '   \033[31mMISSING\033[0m %s\n' "$1"; failures=$((failures + 1))
    fi
}

step "Reset both databases and the log"
for app in shop gateway; do docker compose exec -T "$app" ./gluo --env=dev migrate:reset --yes > /dev/null; done
mkdir -p shop/var/log && : > "$LOG"

step "Payment #1: create — the shop registers it at the gateway"
shop payment:create 100
expect "#1 create   CREATED → PENDING  gateway_ref=1"

step "Gateway: Approve while PENDING — no skipping analysis"
press 1 approve
expect "#1 webhook  PENDING -/-> ?  refused: PENDING→ANALYSIS (PaymentAuthorized: no), PENDING→FAILED (IsAbandoned: no)  gateway=succeeded"

step "Gateway: Review — PENDING → ANALYSIS, the risk team is notified"
press 1 review
expect "#1 webhook  PENDING → ANALYSIS  gateway=requires_review"
expect "#1 action   NotifyRiskTeam: risk team notified"

step "Gateway: Approve — ANALYSIS → PAID, the receipt goes out after the save"
press 1 approve
expect "#1 webhook  ANALYSIS → PAID  gateway=succeeded"
expect "#1 mail     PaymentConfirmed: receipt sent"

step "Slide 12: the gateway delivers the same event again"
press 1 resend
expect "#1 webhook  PAID -/-> ?  refused: PAID→REFUNDED (InRefundWindow: no), PAID→CLOSED (RefundWindowExpired: no)  gateway=succeeded"

step "Slide 27: the admin button and the retry job try to charge again"
shop payment:charge 1
shop payment:retry 1
expect "#1 admin    PAID -/-> PAID  refused: no arrow PAID → PAID"
expect "#1 retry    PAID -/-> PAID  refused: no arrow PAID → PAID"

step "Refund inside the window — PAID → REFUNDED"
shop payment:refund 1
expect "#1 refund   PAID → REFUNDED  days_since_paid=0"
expect "#1 action   RefundMoney: refund issued"

step "Payment #2: review, then Decline — ANALYSIS → FAILED, the customer is told"
shop payment:create 50 > /dev/null
press 2 review
press 2 decline
expect "#2 webhook  ANALYSIS → FAILED  gateway=declined"
expect "#2 action   NotifyCustomer: customer told the payment failed"

step "Payment #3: review, then Expire — nobody says yes (slide 31)"
shop payment:create 75 > /dev/null
press 3 review
press 3 expire
expect "#3 webhook  ANALYSIS -/-> ?  refused: ANALYSIS→PAID (RiskApproved: no), ANALYSIS→FAILED (RiskRejected: no)  gateway=expired"

step "Payment #4: Decline while PENDING — only ANALYSIS knows 'declined'"
shop payment:create 20 > /dev/null
press 4 decline
expect "#4 webhook  PENDING -/-> ?  refused: PENDING→ANALYSIS (PaymentAuthorized: no), PENDING→FAILED (IsAbandoned: no)  gateway=declined"

step "The nightly sweep — nothing is old enough yet"
shop payment:sweep
expect "#4 sweep    PENDING -/-> FAILED  refused: IsAbandoned said no  idle_hours=0"

step "The webhook rejects an event the contract does not know"
status=$(curl -s -o /dev/null -w '%{http_code}' -X POST http://localhost:8080/webhook \
    -H 'Content-Type: application/json' -d '{"payment_id":1,"gateway":"teleported"}')
if [ "$status" = "400" ]; then printf '   \033[32mok\033[0m   400 for gateway=teleported\n'; else printf '   \033[31mGOT %s\033[0m expected 400\n' "$status"; failures=$((failures + 1)); fi

printf '\n\033[1m== The payments log\033[0m\n'
cat "$LOG"

if [ "$failures" -eq 0 ]; then
    printf '\n\033[32mALL GOOD\033[0m — open %s/operator and tail -f %s\n' "$GATEWAY" "$LOG"
else
    printf '\n\033[31m%d CHECK(S) FAILED\033[0m\n' "$failures"
    exit 1
fi
