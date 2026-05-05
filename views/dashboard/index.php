<?php $pageTitle = 'Dashboard'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      Welcome back, <?= htmlspecialchars(($user['first_name'] ?? 'there')) ?> 👋
    </h1>
    <p class="page-subtitle">Here's an overview of your organisation's usage.</p>
  </div>
  <a href="/plans" class="btn btn-primary">Upgrade Plan</a>
</div>

<!-- Stat Cards -->
<div class="card-grid card-grid-4 mb-4">
  <div class="stat-card">
    <div class="stat-label">Credit Balance</div>
    <div class="stat-value"><?= number_format((float) $creditBalance, 0) ?></div>
    <div class="stat-sub">Available credits</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active Plan</div>
    <div class="stat-value" style="font-size:1.3rem;"><?= htmlspecialchars($activePlan['plan_name'] ?? '—') ?></div>
    <div class="stat-sub"><?= $activePlan ? htmlspecialchars($activePlan['billing_cycle'] ?? 'monthly') : 'No active plan' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">API Keys</div>
    <div class="stat-value"><?= (int) $apiKeyCount ?></div>
    <div class="stat-sub">Active keys</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Monthly Credits</div>
    <div class="stat-value" style="font-size:1.3rem;"><?= $activePlan ? number_format((int)($activePlan['credits_monthly'] ?? 0)) : '—' ?></div>
    <div class="stat-sub">Included per month</div>
  </div>
</div>

<!-- Quick Actions -->
<div class="card-grid card-grid-2 mb-4">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Quick Actions</h3>
    </div>
    <div style="display:grid;gap:.75rem;">
      <a href="/api-keys/create" style="display:none;"></a>
      <a href="/api-keys" class="btn btn-secondary">🔑 Manage API Keys</a>
      <a href="/credits/topup"   class="btn btn-ghost">⚡ Top Up Credits</a>
      <a href="/plans"           class="btn btn-ghost">📋 View Plans</a>
      <a href="/billing"         class="btn btn-ghost">💳 Billing &amp; Invoices</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Current Subscription</h3>
      <?php if ($activePlan): ?>
        <span class="badge badge-active">Active</span>
      <?php else: ?>
        <a href="/plans" class="btn btn-primary btn-sm">Subscribe</a>
      <?php endif; ?>
    </div>
    <?php if ($activePlan): ?>
    <div>
      <div style="margin-bottom:.75rem;">
        <span style="font-size:.8rem;color:var(--text-muted);">Plan</span>
        <div style="font-weight:600;"><?= htmlspecialchars($activePlan['plan_name']) ?></div>
      </div>
      <div style="margin-bottom:.75rem;">
        <span style="font-size:.8rem;color:var(--text-muted);">Period Ends</span>
        <div><?= htmlspecialchars(substr($activePlan['current_period_end'] ?? '', 0, 10)) ?></div>
      </div>
      <div>
        <span style="font-size:.8rem;color:var(--text-muted);">Price</span>
        <div style="color:var(--accent);font-weight:700;">
          R<?= number_format((float)($activePlan['price_monthly'] ?? 0), 2) ?> / month
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3>No active subscription</h3>
      <p>Subscribe to a plan to access products and API features.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Transactions -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Recent Credit Transactions</h3>
    <a href="/credits/history" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <?php if (!empty($transactions)): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Balance After</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $tx): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= htmlspecialchars(substr($tx['created_at'], 0, 16)) ?></td>
          <td><?= htmlspecialchars($tx['description']) ?></td>
          <td>
            <span class="badge <?= $tx['type'] === 'credit' ? 'badge-active' : 'badge-revoked' ?>">
              <?= htmlspecialchars($tx['type']) ?>
            </span>
          </td>
          <td style="color:<?= $tx['type'] === 'credit' ? 'var(--success)' : 'var(--danger)' ?>">
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
