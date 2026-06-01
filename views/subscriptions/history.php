<?php $pageTitle = 'Subscription History'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Subscription History</h1>
    <p class="page-subtitle">All subscriptions for your organisation.</p>
  </div>
  <a href="/subscriptions" class="btn">Back to Subscriptions</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">All Subscriptions</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Plan</th>
          <th>Billing</th>
          <th>Status</th>
          <th>Started</th>
          <th>Ended</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allSubs)): ?>
          <tr><td colspan="5" style="text-align:center;padding:1rem;">No subscriptions found.</td></tr>
        <?php else: ?>
          <?php foreach ($allSubs as $sub): ?>
            <tr>
              <td><?= htmlspecialchars($sub['plan_name']) ?></td>
              <td><?= htmlspecialchars(ucfirst($sub['billing_cycle'] ?? '')) ?></td>
              <td>
                <span class="badge badge-<?= ($sub['status'] ?? '') === 'active' ? 'active' : 'revoked' ?>">
                  <?= htmlspecialchars(ucfirst($sub['status'] ?? '')) ?>
                </span>
              </td>
              <td style="font-size:.85rem;color:var(--text-muted);"><?= htmlspecialchars(substr($sub['created_at'] ?? '', 0, 10)) ?></td>
              <td style="font-size:.85rem;color:var(--text-muted);"><?= htmlspecialchars(substr($sub['cancelled_at'] ?? '', 0, 10)) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
