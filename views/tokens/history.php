<?php $pageTitle = 'Token Transactions History'; ?>

<?php
$snap = is_array($accountSnapshot ?? null) ? $accountSnapshot : [
    'balance' => (float) ($balance ?? 0),
    'reserved' => (float) ($reserved ?? 0),
    'available' => (float) ($available ?? ($balance ?? 0)),
];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Token Transactions</h1>
    <p class="page-subtitle">Full ledger of token transactions for your organisation.</p>
  </div>
  <a href="/tokens" class="btn">Back to Tokens</a>
</div>

<div class="card-grid card-grid-3 mb-4">
  <div class="card stat-card">
    <div class="stat-value" style="font-size:1.35rem;"><?= number_format((float) $snap['balance'], 2) ?></div>
    <div class="stat-sub">Total balance</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value" style="font-size:1.35rem;color:var(--warning);"><?= number_format((float) $snap['reserved'], 2) ?></div>
    <div class="stat-sub">Reserved (pending)</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value" style="font-size:1.35rem;color:var(--accent);"><?= number_format((float) $snap['available'], 2) ?></div>
    <div class="stat-sub">Available</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">All Transactions</h3>
  </div>
  <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 1rem 0;">
    Showing up to the latest 100 ledger entries (newest first).
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
        <?php foreach ($transactions as $tx):
            $t = (string) ($tx['type'] ?? '');
            $isTokenIn = \GI\Services\TokenService::isTokenInLedgerType($t);
            $isUsage   = \GI\Services\TokenService::isUsageLedgerType($t);
            $isLock    = \GI\Services\TokenService::isReservationLockType($t);
            $isRelease = \GI\Services\TokenService::isReservationReleaseType($t);
            $badgeClass = \GI\Services\TokenService::ledgerBadgeClass($t);
            $typeLabel  = \GI\Services\TokenService::ledgerTypeLabel($t);
            $amt        = (float) ($tx['amount'] ?? 0);
            if ($isTokenIn) {
                $amtStyle = 'var(--success)';
                $amtPrefix = '+';
            } elseif ($isUsage) {
                $amtStyle = 'var(--danger)';
                $amtPrefix = '-';
            } elseif ($isLock) {
                $amtStyle = 'var(--warning)';
                $amtPrefix = '⏳ ';
            } elseif ($isRelease) {
                $amtStyle = 'var(--text-muted)';
                $amtPrefix = '↩ ';
            } else {
                $amtStyle = 'var(--text-muted)';
                $amtPrefix = '';
            }
            ?>
        <tr>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars(substr((string) ($tx['created_at'] ?? ''), 0, 16)) ?></td>
          <td><?= htmlspecialchars((string) ($tx['description'] ?? '')) ?></td>
          <td>
            <span class="badge badge-<?= $badgeClass ?>">
              <?= htmlspecialchars($typeLabel) ?>
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
    <div class="empty-state-icon">🪙</div>
    <h3>No transactions yet</h3>
    <p>Token transactions will appear here once you start using the platform.</p>
  </div>
  <?php endif; ?>
</div>
