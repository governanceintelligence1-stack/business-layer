<?php $pageTitle = 'Subscriptions'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Subscriptions</h1>
    <p class="page-subtitle">Manage your organisation's subscription and billing cycle.</p>
  </div>
  <a href="/plans" class="btn btn-primary">Browse Plans</a>
</div>

<!-- Current Subscription -->
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Current Subscription</h3>
    <?php if ($currentSub): ?>
      <span class="badge badge-active">Active</span>
    <?php endif; ?>
  </div>

  <?php if ($currentSub): ?>
  <div class="form-row form-row-3 mb-3">
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Plan</div>
      <div style="font-weight:600;font-size:1.1rem;"><?= htmlspecialchars($currentSub['plan_name']) ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Billing Cycle</div>
      <div><?= htmlspecialchars(ucfirst($currentSub['billing_cycle'] ?? 'monthly')) ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Monthly Price</div>
      <div style="color:var(--accent);font-weight:700;">R<?= number_format((float)($currentSub['price_monthly'] ?? 0), 2) ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Tokens / Month</div>
      <div><?= number_format((int)($currentSub['credits_monthly'] ?? 0)) ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Period Start</div>
      <div><?= htmlspecialchars(substr($currentSub['current_period_start'] ?? '', 0, 10)) ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Period End</div>
      <div><?= htmlspecialchars(substr($currentSub['current_period_end'] ?? '', 0, 10)) ?></div>
    </div>
  </div>

  <form method="POST" action="/subscriptions/cancel" style="display:inline;">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
    <button type="submit" class="btn btn-danger btn-sm"
            data-confirm="Are you sure you want to cancel your subscription? This cannot be undone.">
      Cancel Subscription
    </button>
  </form>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon">📋</div>
    <h3>No active subscription</h3>
    <p>Subscribe to a plan to unlock products and API access.</p>
    <a href="/plans" class="btn btn-primary mt-2">View Plans</a>
  </div>
  <?php endif; ?>
</div>

<!-- Subscription History -->
<?php if (!empty($allSubs)): ?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Subscription History</h3>
  </div>
  <?php
    $subscriptions = array_slice($allSubs, 0, 5);
    require __DIR__ . '/_history-table.php';
  ?>
  <div style="margin-top:0.75rem; display:flex; justify-content:space-between; align-items:center;">
    <a href="/subscriptions/history" class="btn">View all subscriptions</a>
    <a href="/subscriptions/transactions" class="btn">View transactions</a>
  </div>
</div>
<?php endif; ?>
