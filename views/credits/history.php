<?php $pageTitle = 'Credit Transactions History'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Credit Transactions</h1>
    <p class="page-subtitle">Full ledger of credit transactions for your organisation.</p>
  </div>
  <a href="/credits" class="btn">Back to Credits</a>
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
</div>
