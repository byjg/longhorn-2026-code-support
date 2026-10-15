# Longhorn PHP 2026: State Machines in PHP (demo code)

The runnable companion to the talk **"Stop Controlling Workflows with If Statements: State
Machines in PHP"**. It holds two small apps and the code from the slides:

```
┌─────────────────────────────┐   POST /transactions    ┌──────────────────────────────┐
│ shop/        :8080          │ ──────────────────────► │ gateway/     :8081           │
│ the merchant app            │                         │ a fake payment provider      │
│ byjg/statemachine           │ ◄────────────────────── │ operator page with buttons   │
│ payments.log                │   POST /webhook         │ keeps every webhook answer   │
└─────────────────────────────┘                         └──────────────────────────────┘
```

- **`shop/`** owns the payment and its state machine. Every door (the webhook, the admin
  button, the retry job, the refund and the nightly sweep) asks the same object, and each
  attempt is one line in `shop/var/log/payments.log`: moved, or refused and why.
- **`gateway/`** knows nothing about state machines. An operator presses *Review / Approve /
  Decline / Expire / Resend*; the gateway reports that event to the shop and shows what the
  shop answered.

Both are [Gluo](https://opensource.byjg.com/docs/php/gluo/) 7.0 projects with their own
SQLite database. The state machine is
[byjg/statemachine](https://github.com/byjg/php-statemachine) 7.0.

## Run it

You need Docker. Nothing else is installed on the host.

```bash
./setup.sh          # build, start, composer install, create the databases
./stage-check.sh    # run the whole stage story and check every step — ends with ALL GOOD
```

- **Gateway (operator page):** <http://localhost:8081/operator>
- **The story, live:** `tail -f shop/var/log/payments.log`
- **Shop commands:** `docker compose exec shop ./gluo payment:<command>`

`stage-check.sh` **resets both databases**. Run it before the talk, not during it.

## Where the slides live

| Slide | What it shows | File |
|---|---|---|
| 21 | The states | [`shop/src/Payment/PaymentState.php`](shop/src/Payment/PaymentState.php) |
| 22–24 | The moves, where the ifs went, where the emails went | [`Condition/RiskApproved.php`](shop/src/Payment/Condition/RiskApproved.php), [`Action/SendReceipt.php`](shop/src/Payment/Action/SendReceipt.php) |
| 26 | The replay is impossible now | `payment:charge` / `payment:retry` on a PAID payment |
| 27 | So where does it live? | [`shop/src/Payment/PaymentMachine.php`](shop/src/Payment/PaymentMachine.php), word for word |
| 29 | The five entry points | [`PaymentService.php`](shop/src/Payment/PaymentService.php), [`PaymentCommands.php`](shop/src/Command/PaymentCommands.php), [`WebhookController.php`](shop/src/Controller/WebhookController.php) |
| 30 | Sometimes the data decides | `PaymentService::gatewayReported()` → `PaymentMover::decide()` → `autoTransitionFrom()` |
| 32 | Persist first, act second | [`PaymentMover.php`](shop/src/Payment/PaymentMover.php): save if unchanged, then `process()` |
| 34–35 | The graph is an executable spec; the guards ride on it | [`shop/tests/Payment/PaymentGraphTest.php`](shop/tests/Payment/PaymentGraphTest.php) |
| 36 | A condition, tested with nothing | [`shop/tests/Payment/ConditionTest.php`](shop/tests/Payment/ConditionTest.php) |
| 37 | What that leaves in your controller | [`shop/src/Controller/WebhookController.php`](shop/src/Controller/WebhookController.php) |

An action tested with a fake mailer, where nothing is sent before `process()`, is in
[`shop/tests/Payment/SendReceiptTest.php`](shop/tests/Payment/SendReceiptTest.php).

The same rules written the "before" way, as plain PHP with ifs and no state machine, are in
[`examples/before/PaymentServiceWithIfs.php`](examples/before/PaymentServiceWithIfs.php). Read it side by
side with [`PaymentMachine.php`](shop/src/Payment/PaymentMachine.php). It is not wired into
the app.

The same graph as data (slide 28) is in
[`shop/src/Payment/payment-machine.yaml`](shop/src/Payment/payment-machine.yaml), next to
`PaymentMachine.php`. The app does not load it: the binding that would is commented out in
[`shop/config/dev/05-services.php`](shop/config/dev/05-services.php), and
[`PaymentYamlTest`](shop/tests/Payment/PaymentYamlTest.php) checks it describes the same machine.

## Stage runbook

Two windows side by side: the browser on **/operator** and a terminal running
`tail -f shop/var/log/payments.log`. A second terminal runs the shop commands. Payment
numbers below assume freshly reset databases (`./stage-check.sh` leaves payments 1–5 behind,
so reset with `./setup.sh` first).

| # | Do | The log shows | The point |
|---|---|---|---|
| 1 | `docker compose exec shop ./gluo payment:create 100` | `CREATED → PENDING  gateway_ref=1` | The shop registers it; the gateway page shows Payment #1 |
| 2 | Press **Approve** on #1 | `PENDING -/-> ?  refused: PENDING→ANALYSIS (PaymentAuthorized: no), PENDING→FAILED (IsAbandoned: no)` | No arrow from PENDING to PAID: you cannot skip analysis (slide 20) |
| 3 | Press **Review** | `PENDING → ANALYSIS`, then `action NotifyRiskTeam` | The data decides (slide 30); the action runs after the save |
| 4 | Press **Approve** | `ANALYSIS → PAID`, then `mail PaymentConfirmed: receipt sent` | RiskApproved guards one arrow (slide 23); the receipt goes out once |
| 5 | Press **Resend** | `PAID -/-> ?  refused: …` | The gateway delivers twice — nothing happens (slide 12) |
| 6 | `… payment:charge 1` then `… payment:retry 1` | `PAID -/-> PAID  refused: no arrow PAID → PAID` | The second charge cannot be expressed (slide 26) |
| 7 | `… payment:refund 1` | `PAID → REFUNDED`, then `action RefundMoney` | A guard on time, fed by data, not by a clock |
| 8 | `… payment:create 50`, **Review**, **Decline** | `ANALYSIS → FAILED`, then `action NotifyCustomer` | Same state, different arrow, different effect |
| 9 | `… payment:create 75`, **Review**, **Expire** | `ANALYSIS -/-> ?  refused: …RiskApproved: no… RiskRejected: no` | "null — nobody says yes" (slide 30) |

Questions you may get, with a live answer:
- **"What if the gateway is down?"** `docker compose stop gateway`, then `payment:create 30`:
  the payment stays CREATED and says why. `docker compose start gateway` brings it back.
- **"Decline while PENDING?"** It is refused: only ANALYSIS → FAILED checks `declined`. The
  graph is exactly slide 27, and the missing arrow is a design decision, visible in one file.
- **"Two writers at the same instant?"** `PaymentRepository::saveMove()` only saves if the row
  still holds the state the move was decided from (`ASaveFromAStaleStateIsRejected` test).
- **"Is the HTTP contract enforced?"** An unknown event is a 400: the webhook body is
  validated against its OpenAPI schema before any code runs.

## Tests

```bash
docker compose exec -T -e APP_ENV=test shop composer test
docker compose exec -T -e APP_ENV=test gateway composer test
docker compose exec -T shop composer psalm
docker compose exec -T gateway composer psalm
```

The shop's tests are where the talk's testing claims are made concrete:

- **[`ConditionTest`](shop/tests/Payment/ConditionTest.php):** every guard, alone.
- **[`SendReceiptTest`](shop/tests/Payment/SendReceiptTest.php):** an action, through the machine.
- **[`PaymentGraphTest`](shop/tests/Payment/PaymentGraphTest.php):** the graph table, the rules table, and the wiring of all seven arrows.
- **[`PaymentServiceTest`](shop/tests/Payment/PaymentServiceTest.php):** every door on a real (test) database.
- **[`PaymentMoverTest`](shop/tests/Payment/PaymentMoverTest.php):** the save-then-`process()` protocol, refusal reasons and the race.
- **[`WebhookTest`](shop/tests/Controller/WebhookTest.php):** the HTTP contract.

## Notes

- **The two apps talk over the compose network.** The gateway reaches the shop at
  `http://shop/webhook`, and the shop reaches the gateway at `http://gateway`. Both URLs are
  set in each project's `config/*/credentials.env`.
- **Every command runs inside a container.** SQLite paths are absolute container paths
  (`/srv/db/dev.sqlite`), so `./gluo` and `composer` belong inside `docker compose exec`.
- **The shop's `PaymentMachine` is built by the container.**
  `DI::bind(PaymentMachine::class)->withFactoryMethod('build', [Param::container()])` in
  [`shop/config/dev/05-services.php`](shop/config/dev/05-services.php), so every condition
  and action named on slide 27 is resolved, with its dependencies, when the machine is built.
