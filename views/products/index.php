<?php $pageTitle = 'Products'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Products</h1>
    <p class="page-subtitle">Governance intelligence products available on the platform.</p>
  </div>
  <a href="/plans" class="btn btn-primary">Upgrade for Access</a>
</div>

<?php if (!empty($products)): ?>
<div class="product-cards">
  <?php foreach ($products as $product): ?>
  <?php $hasAccess = $product['has_access'] ?? false; ?>
  <div class="product-card <?= $hasAccess ? 'has-access' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
      <div class="product-icon"><?= htmlspecialchars($product['icon'] ?? '📊') ?></div>
      <?php if ($hasAccess): ?>
        <span class="badge badge-active">✓ Access</span>
      <?php else: ?>
        <span class="badge badge-pending">Locked</span>
      <?php endif; ?>
    </div>
    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
    <div class="product-desc"><?= htmlspecialchars($product['description'] ?? '') ?></div>
    <?php if (!empty($product['credit_cost'])): ?>
      <div class="product-credit">⚡ <?= number_format((float)$product['credit_cost'], 2) ?> credits / call</div>
    <?php endif; ?>
    <?php if (!empty($product['slug'])): ?>
      <div class="mt-2" style="font-size:.75rem;color:var(--text-muted);">Slug: <code style="color:var(--accent);"><?= htmlspecialchars($product['slug']) ?></code></div>
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
