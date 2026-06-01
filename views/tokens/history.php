<?php
$pageTitle = 'Token Transactions History';
$balance   = (float) ($balance ?? 0);
$reserved  = (float) ($reserved ?? 0);
$available = (float) ($available ?? ($balance - $reserved));
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
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Total Balance</h3>
    </div>
    <div class="stat-value" style="font-size:1.5rem;"><?= number_format($balance, 2) ?></div>
  </div>
  <div class="card stat-card">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Pending (Reserved)</h3>
    </div>
    <div class="stat-value" style="font-size:1.5rem;"><?= number_format($reserved, 2) ?></div>
  </div>
  <div class="card stat-card">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Available</h3>
    </div>
    <div class="stat-value" style="font-size:1.5rem;"><?= number_format($available, 2) ?></div>
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
            $isReserve = \GI\Services\TokenService::isReserveLedgerType($t);
            $isRelease = \GI\Services\TokenService::isReleaseLedgerType($t);
            if ($isTokenIn) {
                $badgeClass = 'active';
            } elseif ($isUsage) {
                $badgeClass = 'revoked';
            } else {
                $badgeClass = 'pending';
            }
            $amt = (float) ($tx['amount'] ?? 0);
            if ($isTokenIn) {
                $amtStyle = 'var(--success)';
                $amtPrefix = '+';
            } elseif ($isUsage) {
                $amtStyle = 'var(--danger)';
                $amtPrefix = '-';
            } elseif ($isReserve) {
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
    <div class="empty-state-icon">🪙</div>
    <h3>No transactions yet</h3>
    <p>Token transactions will appear here once you start using the platform.</p>
  </div>
  <?php endif; ?>
</div>
