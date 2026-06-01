<?php
$pageTitle = 'Tokens';

$balance   = (float) ($balance ?? 0);
$reserved  = (float) ($reserved ?? 0);
$available = (float) ($available ?? ($balance - $reserved));
$pendingReservations = is_array($pendingReservations ?? null) ? $pendingReservations : [];

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
        if (!\GI\Services\TokenService::isUsageLedgerType((string) ($tx['type'] ?? ''))) {
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
    <h1 class="page-title">Tokens</h1>
    <p class="page-subtitle">Total balance, pending job holds, and what you can still spend.</p>
  </div>
</div>

<div class="card-grid card-grid-3 mb-4">
  <div class="card stat-card">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Total Balance</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format($balance, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">All tokens in your account</div>
    </div>
  </div>

  <div class="card stat-card" style="--accent:#f59e0b;">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Pending (Reserved)</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format($reserved, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">
        <?= count($pendingReservations) ?> active job<?= count($pendingReservations) === 1 ? '' : 's' ?>
      </div>
    </div>
  </div>

  <div class="card stat-card" style="--accent:#4caf50;">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Available</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format($available, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">Balance minus pending holds</div>
    </div>
  </div>
</div>

<div class="card-grid card-grid-2 mb-4">
  <div class="card stat-card">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Usage This Month</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.5rem;"><?= number_format($monthTotal, 2) ?></div>
      <div class="stat-sub">Tokens consumed (<?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?>)</div>
      <div class="stat-sub" style="margin-top:0.25rem;font-size:0.72rem;">
        Captured job usage and direct debits in your ledger for this calendar month.
      </div>
    </div>
  </div>

  <div class="card" style="display:flex;flex-direction:column;justify-content:center;">
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0;">
      Jobs <strong>reserve</strong> estimated tokens before they run. On success, reserved tokens are <strong>captured</strong>; on failure they are <strong>released</strong>.
      Available = total balance − pending reserved.
    </p>
  </div>
</div>

<?php if (!empty($pendingReservations)): ?>
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Pending job reservations</h3>
    <span class="badge badge-pending"><?= count($pendingReservations) ?> pending</span>
  </div>
  <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 1rem 0;">
    Tokens held until each job completes (capture) or fails (release).
  </p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reserved</th>
          <th>Reference</th>
          <th>Job ID</th>
          <th>Since</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingReservations as $row): ?>
        <tr>
          <td style="font-weight:600;color:var(--warning);">
            <?= number_format((float) ($row['estimated_credits'] ?? 0), 2) ?>
          </td>
          <td><code style="font-size:.8rem;"><?= htmlspecialchars((string) ($row['reservation_reference'] ?? '')) ?></code></td>
          <td style="font-size:.75rem;color:var(--text-muted);">
            <code><?= htmlspecialchars(substr((string) ($row['job_id'] ?? ''), 0, 18)) ?>…</code>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted);">
            <?= htmlspecialchars(substr((string) ($row['created_at'] ?? ''), 0, 16)) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Recent token transactions</h3>
  </div>
  <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 1rem 0;">
    Last 100 ledger entries (newest first).
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
            $isTokenIn = \GI\Services\TokenService::isTokenInLedgerType($t);
            $isUsage   = \GI\Services\TokenService::isUsageLedgerType($t);
            $isReserve = \GI\Services\TokenService::isReserveLedgerType($t);
            $isRelease = \GI\Services\TokenService::isReleaseLedgerType($t);
            if ($isTokenIn) {
                $badgeClass = 'active';
            } elseif ($isUsage) {
                $badgeClass = 'revoked';
            } elseif ($isReserve || $isRelease) {
                $badgeClass = 'pending';
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
  <div style="margin-top:0.75rem; display:flex; justify-content:flex-end; gap:0.5rem;">
    <a href="/tokens/history" class="btn">View all transactions</a>
  </div>
</div>
