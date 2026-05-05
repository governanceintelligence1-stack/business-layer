<?php $pageTitle = 'Plans'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Plans</h1>
    <p class="page-subtitle">Choose the plan that suits your organisation's needs.</p>
  </div>
</div>

<?php if (!empty($plans)): ?>
<div class="pricing-cards">
  <?php
  $planCount = count($plans);
  $middleIdx = (int) floor(($planCount - 1) / 2);
  foreach ($plans as $i => $plan):
    $featured = ($planCount > 1 && $i === $middleIdx) ? 'featured' : '';
    $features = $plan['features'] ?? [];
    if (!is_array($features)) {
        $features = json_decode((string)$features, true) ?? [];
    }
  ?>
  <div class="pricing-card <?= $featured ?>">
    <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
    <div class="plan-desc"><?= htmlspecialchars($plan['description'] ?? '') ?></div>
    <div class="plan-price">
      <div class="plan-amount"><sup>R</sup><?= number_format((float)($plan['price_monthly'] ?? 0), 0) ?></div>
      <div class="plan-period">/ month</div>
    </div>

    <div style="margin-bottom:1rem;padding:.75rem;background:rgba(200,168,75,.06);border-radius:6px;font-size:.85rem;">
      <div>⚡ <strong style="color:var(--accent);"><?= number_format((int)($plan['credits_monthly'] ?? 0)) ?></strong> credits / month</div>
      <?php if (!empty($plan['max_users'])): ?>
      <div style="margin-top:.3rem;">👥 Up to <?= (int)$plan['max_users'] ?> users</div>
      <?php endif; ?>
      <?php if (!empty($plan['max_api_keys'])): ?>
      <div style="margin-top:.3rem;">🔑 Up to <?= (int)$plan['max_api_keys'] ?> API keys</div>
      <?php endif; ?>
    </div>

    <?php if (!empty($plan['products'])): ?>
    <div style="margin-bottom:1rem;">
      <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.05em;">Included Products</div>
      <?php foreach ($plan['products'] as $p): ?>
        <span class="badge badge-gold" style="margin:.15rem .15rem .15rem 0;"><?= htmlspecialchars($p['name']) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($features)): ?>
    <ul class="plan-features">
      <?php foreach ($features as $f): ?>
        <li><?= htmlspecialchars(is_array($f) ? ($f['label'] ?? '') : (string)$f) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <form method="POST" action="/subscriptions/subscribe/<?= htmlspecialchars($plan['id']) ?>">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
      <input type="hidden" name="billing_cycle" value="monthly">
      <button type="submit" class="btn btn-<?= $featured ? 'primary' : 'secondary' ?> w-100"
              data-confirm="Subscribe to <?= htmlspecialchars($plan['name']) ?>?">
        Subscribe
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <div class="empty-state-icon">📋</div>
  <h3>No plans available</h3>
  <p>Plans will appear here once configured.</p>
</div>
<?php endif; ?>
