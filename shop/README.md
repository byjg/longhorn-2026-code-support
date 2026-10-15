# byjg/longhorn-shop

The merchant app of the [Longhorn 2026 demo](../README.md): the payment, its state machine
([byjg/statemachine](https://github.com/byjg/php-statemachine) 7.0), the webhook and the
payment commands. Built with [Gluo](https://opensource.byjg.com/docs/php/gluo/) on SQLite;
the application code lives in `src/` under the `Shop` namespace.

Run it from the repository root (`./setup.sh`). Every command below runs inside the
container: `docker compose exec shop <command>`.

## Where things are

| | |
|---|---|
| `src/Payment/PaymentState.php` | The seven states (slide 21) |
| `src/Payment/PaymentMachine.php` | The graph, the guards and the effects (slide 27) |
| `src/Payment/Condition/`, `src/Payment/Action/` | The six guards and the four effects |
| `src/Payment/PaymentService.php` | The doors: each turns what it knows into the data the guards read |
| `src/Payment/PaymentMover.php` | Every move goes through it: ask, save if unchanged, then `process()` (slide 32) |
| `src/Controller/WebhookController.php` | `POST /webhook`: the gateway reports, the machine decides (slide 37) |
| `src/Command/PaymentCommands.php` | `./gluo payment:create\|charge\|retry\|refund\|sweep` |
| `config/dev/05-services.php` | The container wiring, including `PaymentMachine::build()` |
| `var/log/payments.log` | One line per move, refusal and action |

## Commands

| Command | What it does |
|---|---|
| `./gluo payment:create <amount>` | Create a payment and register it at the gateway: CREATED → PENDING |
| `./gluo payment:charge <id>` | The admin button: ask for PAID |
| `./gluo payment:retry <id>` | The retry job: ask for PAID again |
| `./gluo payment:refund <id>` | PAID → REFUNDED, inside the refund window |
| `./gluo payment:sweep` | Fail abandoned payments, close the ones past the refund window |
| `APP_ENV=test composer test` | Run the test suite |
| `composer psalm` | Static analysis |
| `./gluo list --check` | List every command and validate it |
| `./gluo openapi` | Regenerate `public/docs/openapi.json` after changing an endpoint |
