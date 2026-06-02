<?php $pageTitle = 'Products'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Products</h1>
    <p class="page-subtitle">
      Every plan includes all products. Usage is limited by your monthly token balance
      (<?= number_format((float)($minimumMonthlyTokens ?? 0), 0) ?> tokens covers one use of each product).
    </p>
  </div>
  <?php if (!($hasSubscription ?? false)): ?>
    <a href="/plans" class="btn btn-primary">Get a Subscription</a>
  <?php else: ?>
    <a href="/tokens" class="btn btn-primary">View Token Balance</a>
  <?php endif; ?>
</div>

<?php if (($hasSubscription ?? false)): ?>
<p class="text-muted" style="margin: -0.5rem 0 1.25rem; font-size: 0.9rem;">
  Available tokens: <strong><?= number_format((float)($availableBalance ?? 0), 2) ?></strong>
</p>
<?php endif; ?>

<?php if (!empty($products)): ?>
<div class="product-cards">
  <?php foreach ($products as $product): ?>
  <?php
    $canUse = $product['has_access'] ?? false;
    $reason = (string) ($product['access_reason'] ?? '');
    $cost   = (float) ($product['token_cost'] ?? $product['credit_cost'] ?? 0);
  ?>
  <div class="product-card <?= $canUse ? 'has-access' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
      <div class="product-icon"><?= htmlspecialchars($product['icon'] ?? '📊') ?></div>
      <?php if ($canUse): ?>
        <span class="badge badge-active">Ready</span>
      <?php elseif ($reason === 'Insufficient tokens'): ?>
        <span class="badge badge-pending">Need tokens</span>
      <?php elseif ($reason === 'No active subscription'): ?>
        <span class="badge badge-pending">Subscribe</span>
      <?php else: ?>
        <span class="badge badge-pending">Unavailable</span>
      <?php endif; ?>
    </div>
    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
    <div class="product-desc"><?= htmlspecialchars($product['description'] ?? '') ?></div>
    <?php if ($cost > 0): ?>
      <div class="product-credit">⚡ <?= number_format($cost, 2) ?> tokens / use</div>
    <?php endif; ?>
    <?php if (!$canUse && $reason === 'Insufficient tokens'): ?>
      <div class="mt-2"><a href="/tokens" class="btn btn-secondary btn-sm">View tokens</a></div>
    <?php elseif (!$canUse && $reason === 'No active subscription'): ?>
      <div class="mt-2"><a href="/plans" class="btn btn-secondary btn-sm">View plans</a></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <div class="empty-state-icon">📦</div>
  <h3>No products available</h3>
  <p>Products will appear here once configured in the platform.</p>
</div>
<?php endif; ?>
