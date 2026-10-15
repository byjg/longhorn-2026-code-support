# byjg/longhorn-gateway

The fake payment provider of the [Longhorn 2026 demo](../README.md). It records the payments
the shop registers, lets an operator decide what "happened" to each one, reports that event to
the shop's webhook and keeps the answer. It knows nothing about state machines. Built with
[Gluo](https://opensource.byjg.com/docs/php/gluo/) on SQLite; the application code lives in
`src/` under the `Gateway` namespace.

Run it from the repository root (`./setup.sh`), then open <http://localhost:8081/operator>.

## Where things are

| | |
|---|---|
| `src/Gateway/GatewayService.php` | Register a payment; map a button to an event; POST the webhook; keep the answer |
| `src/Gateway/GatewayPage.php` | The operator page: plain forms, refreshes every 3 seconds |
| `src/Controller/TransactionController.php` | `POST /transactions`, `GET /operator`, `POST /transactions/{id}/{action}` |
| `src/Command/GatewayCommands.php` | The same buttons as `./gluo gateway:<action> <id>` |

| Button | Event sent to the shop |
|---|---|
| Review | `requires_review` |
| Approve | `succeeded` |
| Decline | `declined` |
| Expire | `expired` |
| Resend | the last event again |

## Commands

Every command runs inside the container: `docker compose exec gateway <command>`.

| Command | What it does |
|---|---|
| `./gluo gateway:review\|approve\|decline\|expire\|resend <id>` | Press a button from the terminal; prints what was sent and what the shop answered |
| `APP_ENV=test composer test` | Run the test suite |
| `composer psalm` | Static analysis |
| `./gluo openapi` | Regenerate `public/docs/openapi.json` after changing an endpoint |
