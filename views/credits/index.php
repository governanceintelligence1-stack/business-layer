<?php $pageTitle = 'Credits'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Credits</h1>
    <p class="page-subtitle">Manage your credit balance and transaction history.</p>
  </div>
  <button class="btn btn-primary" data-modal-open="topup-modal">⚡ Top Up Credits</button>
</div>

<!-- Balance Cards -->
<div class="card-grid card-grid-2 mb-4">
  <div class="stat-card">
    <div class="stat-label">Current Balance</div>
    <div class="stat-value"><?= number_format((float) $balance, 2) ?></div>
    <div class="stat-sub">Available credits</div>
  </div>
  <div class="stat-card" style="--accent:#4caf50;">
    <div class="stat-label">Usage This Month</div>
    <div class="stat-value" style="font-size:1.5rem;">
      <?php
      $used = array_reduce($transactions ?? [], function($carry, $tx) {
          return $carry + ($tx['type'] === 'debit' ? (float)$tx['amount'] : 0);
      }, 0.0);
      echo number_format($used, 2);
      ?>
    </div>
    <div class="stat-sub">Credits consumed</div>
  </div>
</div>

<!-- Transaction History -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Transaction History</h3>
  </div>
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
        <?php foreach ($transactions as $tx): ?>
        <tr>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars(substr($tx['created_at'], 0, 16)) ?></td>
          <td><?= htmlspecialchars($tx['description']) ?></td>
          <td>
            <span class="badge badge-<?= $tx['type'] === 'credit' ? 'active' : 'revoked' ?>">
              <?= htmlspecialchars($tx['type']) ?>
            </span>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted);">
            <?= htmlspecialchars($tx['ref_type'] ?? '') ?>
            <?= !empty($tx['ref_id']) ? '<br><code style="font-size:.75rem;">' . htmlspecialchars(substr($tx['ref_id'], 0, 12)) . '…</code>' : '' ?>
          </td>
          <td style="font-weight:600;color:<?= $tx['type'] === 'credit' ? 'var(--success)' : 'var(--danger)' ?>">
            <?= $tx['type'] === 'credit' ? '+' : '-' ?><?= number_format((float)$tx['amount'], 2) ?>
          </td>
          <td style="color:var(--accent);"><?= number_format((float)$tx['balance_after'], 2) ?></td>
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

<!-- Top-Up Modal -->
<div id="topup-modal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3>Top Up Credits</h3>
      <button class="modal-close">&times;</button>
    </div>
    <form method="POST" action="/credits/topup">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
      <div class="form-group">
        <label class="form-label">Credit Amount</label>
        <input type="number" name="amount" class="form-control" min="100" step="100"
               placeholder="e.g. 5000" required>
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:.3rem;">Minimum: 100 credits</div>
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary w-100">Add Credits</button>
      </div>
    </form>
  </div>
</div>
