<?php

namespace Gateway\Gateway;

use Gateway\Model\Transaction;
use Gateway\Model\WebhookCall;
use Gateway\Repository\TransactionRepository;
use Gateway\Repository\WebhookCallRepository;

/**
 * The operator page, as one HTML string: plain forms, no JavaScript framework, no build step.
 * It refreshes itself every few seconds, so payments created from the shop's CLI appear on
 * the projector without touching the browser.
 */
class GatewayPage
{
    private const REFRESH_SECONDS = 3;

    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly WebhookCallRepository $calls,
    ) {
    }

    public function render(): string
    {
        $rows = array_map(fn (Transaction $t): string => $this->row($t), $this->transactions->newestFirst());
        $body = $rows === []
            ? '<p class="empty">No payments yet. In the shop: <code>./gluo payment:create 100</code></p>'
            : implode("\n", $rows);
        $refresh = self::REFRESH_SECONDS;

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="refresh" content="{$refresh}">
<title>Gateway</title>
<style>
  :root { --ink: #23272F; --muted: #6B7280; --brand: #3A4380; --line: #E5E7EB; --code: #1E2130; --ok: #1F7A4D; --no: #B42318; }
  body { margin: 0; font: 18px/1.45 "Open Sans", system-ui, sans-serif; color: var(--ink); background: #F5F6FA; }
  header { background: var(--brand); color: #fff; padding: 18px 32px; }
  header h1 { margin: 0; font-size: 28px; }
  header p { margin: 4px 0 0; opacity: .8; font-size: 15px; }
  main { padding: 24px 32px; display: grid; gap: 18px; }
  .tx { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 18px 22px; }
  .head { display: flex; gap: 24px; align-items: baseline; flex-wrap: wrap; }
  .head strong { font-size: 22px; }
  .status { font-family: "JetBrains Mono", monospace; background: #EEF0FA; color: var(--brand); padding: 2px 10px; border-radius: 6px; }
  form { display: inline; }
  .buttons { margin: 14px 0; display: flex; gap: 8px; flex-wrap: wrap; }
  button { font: inherit; font-size: 16px; padding: 6px 16px; border-radius: 6px; border: 1px solid var(--brand); background: #fff; color: var(--brand); cursor: pointer; }
  button:hover { background: var(--brand); color: #fff; }
  table { width: 100%; border-collapse: collapse; font-size: 15px; }
  td, th { text-align: left; padding: 6px 8px; border-top: 1px solid var(--line); vertical-align: top; }
  th { color: var(--muted); font-weight: 600; }
  code, .answer { font-family: "JetBrains Mono", monospace; font-size: 14px; }
  .answer { white-space: pre-wrap; word-break: break-word; }
  .moved { color: var(--ok); } .refused { color: var(--no); }
  .empty { color: var(--muted); }
</style>
</head>
<body>
<header>
  <h1>Payment gateway</h1>
  <p>Press a button: the gateway reports that event to the shop's webhook and shows what the shop answered.</p>
</header>
<main>
{$body}
</main>
</body>
</html>
HTML;
    }

    private function row(Transaction $transaction): string
    {
        $id = (int)$transaction->getId();
        $buttons = '';
        foreach ([...array_keys(GatewayService::ACTIONS), 'resend'] as $action) {
            $buttons .= sprintf(
                '<form method="post" action="/transactions/%d/%s"><button>%s</button></form>',
                $id, $action, ucfirst($action)
            );
        }

        $history = array_map(fn (WebhookCall $c): string => $this->call($c), array_slice($this->calls->forTransaction($id), 0, 5));
        $table = $history === []
            ? '<p class="empty">No webhook sent yet.</p>'
            : '<table><tr><th>sent</th><th>event</th><th>shop answered</th></tr>' . implode('', $history) . '</table>';

        return sprintf(
            '<section class="tx"><div class="head"><strong>Payment #%d</strong><span>%s</span><span class="status">%s</span><span class="empty">gateway ref %d</span></div><div class="buttons">%s</div>%s</section>',
            (int)$transaction->getPaymentId(),
            number_format((float)$transaction->getAmount(), 2),
            $this->e((string)$transaction->getStatus()),
            $id,
            $buttons,
            $table
        );
    }

    private function call(WebhookCall $call): string
    {
        $answer = (string)$call->getResponseBody();
        $decoded = json_decode($answer, true);
        $class = '';
        if (is_array($decoded)) {
            $class = isset($decoded['to']) ? 'moved' : (($decoded['result'] ?? null) === 'refused' ? 'refused' : '');
            // Readable on the projector: "→", not "\u2192"
            $answer = (string)json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return sprintf(
            '<tr><td>%s</td><td><code>%s</code></td><td class="answer %s">%d %s</td></tr>',
            $this->e(substr((string)$call->getCreatedAt(), 11, 8)),
            $this->e((string)$call->getEvent()),
            $class,
            (int)$call->getResponseStatus(),
            $this->e($answer)
        );
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}
