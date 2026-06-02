<?php
$pageTitle = 'Tokens';

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
    <p class="page-subtitle">Manage your token balance and transaction history.</p>
  </div>
</div>

<?php
$snap = is_array($accountSnapshot ?? null) ? $accountSnapshot : [
    'balance' => (float) ($balance ?? 0),
    'reserved' => (float) ($reserved ?? 0),
    'available' => (float) ($available ?? ($balance ?? 0)),
];
$balTotal = (float) ($snap['balance'] ?? 0);
$balReserved = (float) ($snap['reserved'] ?? 0);
$balAvailable = (float) ($snap['available'] ?? ($balTotal - $balReserved));
$pending = is_array($pendingReservations ?? null) ? $pendingReservations : [];
?>

<div class="card-grid card-grid-3 mb-4">
  <div class="card stat-card">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Total balance</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format($balTotal, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">Tokens in your account</div>
    </div>
  </div>

  <div class="card stat-card" style="--accent:#ff9800;">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Reserved (pending)</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format($balReserved, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">
        <?php if ($balReserved > 0): ?>
          Locked for <?= count($pending) ?> active job<?= count($pending) === 1 ? '' : 's' ?>
        <?php else: ?>
          No jobs holding tokens right now
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card stat-card" style="--accent:#2196f3;">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Available</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.75rem;"><?= number_format($balAvailable, 2) ?></div>
      <div class="stat-sub" style="margin-top:0.5rem;">Balance minus reserved (what new jobs can use)</div>
    </div>
  </div>
</div>

<div class="card-grid card-grid-2 mb-4">
  <div class="card stat-card" style="--accent:#4caf50;">
    <div class="card-header" style="margin-bottom:0.5rem;">
      <h3 class="card-title" style="font-size:0.95rem;">Usage This Month</h3>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.5rem;"><?= number_format($monthTotal, 2) ?></div>
      <div class="stat-sub">Tokens consumed (<?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?>)</div>
      <div class="stat-sub" style="margin-top:0.25rem;font-size:0.72rem;">
        Job captures, direct debits, and legacy debit rows in your ledger for this calendar month (local timezone).
      </div>
    </div>
  </div>
</div>

<?php if (!empty($pending)): ?>
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Pending job reservations</h3>
  </div>
  <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 1rem 0;">
    Tokens reserved before jobs finish. Released on failure; captured on success.
  </p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reserved</th>
          <th>Reference</th>
          <th>Job</th>
          <th>Since</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $row):
            $meta = [];
            if (!empty($row['metadata'])) {
                $decoded = json_decode((string) $row['metadata'], true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            $clientJob = (string) ($meta['client_job_id'] ?? '');
            $jobDisplay = $clientJob !== '' ? $clientJob : substr((string) ($row['job_id'] ?? ''), 0, 8) . '…';
            ?>
        <tr>
          <td style="font-weight:600;"><?= number_format((float) ($row['estimated_credits'] ?? 0), 2) ?></td>
          <td><code style="font-size:.75rem;"><?= htmlspecialchars((string) ($row['reservation_reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
          <td style="font-size:.8rem;"><code><?= htmlspecialchars($jobDisplay, ENT_QUOTES, 'UTF-8') ?></code></td>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars(substr((string) ($row['created_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge badge-pending">Pending</span></td>
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
  <div style="margin-top:0.75rem; display:flex; justify-content:flex-end; gap:0.5rem;">
    <a href="/tokens/history" class="btn">View all transactions</a>
  </div>
</div>
