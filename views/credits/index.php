<?php
$pageTitle = 'Credits';

$monthUsage = is_array($monthUsage ?? null) ? $monthUsage : [
    'total'               => 0.0,
    'label'               => date('F Y'),
    'range_start'         => '',
    'range_end_exclusive' => '',
];

$monthTotal = (float) ($monthUsage['total'] ?? 0);
$monthLabel = (string) ($monthUsage['label'] ?? date('F Y'));
$rangeStart = (string) ($monthUsage['range_start'] ?? '');
$rangeEndEx = (string) ($monthUsage['range_end_exclusive'] ?? '');

$listedMonthUsage = 0.0;
if ($rangeStart !== '' && $rangeEndEx !== '') {
    foreach ($transactions ?? [] as $tx) {
        if (!\GI\Services\CreditService::isUsageLedgerType((string) ($tx['type'] ?? ''))) {
            continue;
        }
        $d = substr((string) ($tx['created_at'] ?? ''), 0, 10);
        if ($d !== '' && $d >= $rangeStart && $d < $rangeEndEx) {
            $listedMonthUsage += (float) ($tx['amount'] ?? 0);
        }
    }
}
$usageListIncomplete = ($monthTotal > 0.00001) && ($listedMonthUsage + 0.0001 < $monthTotal);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Credits</h1>
    <p class="page-subtitle">Manage your credit balance and transaction history.</p>
  </div>
</div>

<!-- Balance Cards -->
<div class="card-grid card-grid-2 mb-4">
  <div class="card stat-card">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Current Balance</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format((float) $balance, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">Available credits</div>
    </div>
  </div>

  <div class="card stat-card" style="--accent:#4caf50;">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Usage This Month</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.5rem;"><?= number_format($monthTotal, 2) ?></div>
      <div class="stat-sub">Credits consumed (<?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?>)</div>
      <div class="stat-sub" style="margin-top:0.25rem;font-size:0.72rem;">
        Job captures, direct debits, and legacy debit rows in your ledger for this calendar month (local timezone).
      </div>
    </div>
  </div>
</div>

<!-- Transaction History -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Recent credit transactions</h3>
  </div>
  <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 1rem 0;">
    Last 100 ledger entries (newest first). Amount signs follow the same rules as the month usage total above.
    <?php if ($usageListIncomplete) : ?>
      <span style="display:block;margin-top:0.35rem;color:var(--warning);">
        Some <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?> usage is not shown in this list; the <strong>Usage This Month</strong> figure is the full ledger total for the month.
      </span>
    <?php endif; ?>
  </p>
  <?php if (!empty($transactions)): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date &amp; Time</th>
          <th>Description</th>
          <th>Type</th>
          <th>Ref</th>
          <th>Amount</th>
          <th>Balance After</th>
        </tr>
      </thead>
      <tbody>
        <?php $displayTransactions = is_array($transactions) ? array_slice($transactions, 0, 5) : []; ?>
        <?php foreach ($displayTransactions as $tx):
            $t = (string) ($tx['type'] ?? '');
            $isCreditIn = \GI\Services\CreditService::isCreditInLedgerType($t);
            $isUsage    = \GI\Services\CreditService::isUsageLedgerType($t);
            $badgeClass = $isCreditIn ? 'active' : ($isUsage ? 'revoked' : 'pending');
            $amt        = (float) ($tx['amount'] ?? 0);
            if ($isCreditIn) {
                $amtStyle = 'var(--success)';
                $amtPrefix = '+';
            } elseif ($isUsage) {
                $amtStyle = 'var(--danger)';
                $amtPrefix = '-';
            } else {
                $amtStyle = 'var(--text-muted)';
                $amtPrefix = '';
            }
            ?>
        <tr>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars(date('j M H:i', strtotime((string) ($tx['created_at'] ?? '')))) ?></td>
          <td><?= htmlspecialchars((string) ($tx['description'] ?? '')) ?></td>
          <td>
            <span class="badge badge-<?= $badgeClass ?>">
              <?= htmlspecialchars($t !== '' ? $t : '—') ?>
            </span>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted);">
            <?= htmlspecialchars((string) ($tx['ref_type'] ?? '')) ?>
            <?= !empty($tx['ref_id']) ? '<br><code style="font-size:.75rem;">' . htmlspecialchars(substr((string) $tx['ref_id'], 0, 12)) . '…</code>' : '' ?>
          </td>
          <td style="font-weight:600;color:<?= $amtStyle ?>">
            <?= $amtPrefix ?><?= number_format($amt, 2) ?>
          </td>
          <td style="color:var(--accent);"><?= number_format((float) ($tx['balance_after'] ?? 0), 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon">💳</div>
    <h3>No transactions yet</h3>
    <p>Credit transactions will appear here once you start using the platform.</p>
  </div>
  <?php endif; ?>
  <div style="margin-top:0.75rem; display:flex; justify-content:flex-end; gap:0.5rem;">
    <a href="/credits/history" class="btn">View all transactions</a>
  </div>
</div>

<!-- Top-ups are only available via subscriptions; manual top-up UI removed -->
