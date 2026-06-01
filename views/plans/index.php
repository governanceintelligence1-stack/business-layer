<?php $pageTitle = 'Subscription Plans'; ?>

<style>
/* ============================================================
   shadcn / Flux UI Inspired Theme
   ============================================================ */

:root {
  --background: #ffffff;
  --foreground: #09090b;
  --card: #ffffff;
  --card-foreground: #09090b;
  --primary: #09090b;
  --primary-foreground: #fafafa;
  --secondary: #f4f4f5;
  --secondary-foreground: #18181b;
  --muted: #f4f4f5;
  --muted-foreground: #71717a;
  --accent: #f4f4f5;
  --accent-foreground: #18181b;
  --border: #e4e4e7;
  --radius: 0.75rem;
  --radius-sm: 0.5rem;
  --font-sans: Inter, Roboto, "Segoe UI", sans-serif;
}

body {
  overflow: hidden;
}

.plans-shell {
  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  height: calc(100vh - 4rem);
  padding: 4.75rem 1rem 0;
  overflow: hidden;
  box-sizing: border-box;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0;
  position: fixed;
  top: 0;
  left: 268px; /* Matches sidebar width */
  right: 0;
  z-index: 60;
  background: white;
  padding: 0.8rem 2rem 0.7rem;
  border-bottom: 1px solid var(--border);
}

.page-eyebrow {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  margin-bottom: 0.5rem;
  padding: 0.25rem 0.625rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--background);
  color: var(--muted-foreground);
  font-size: 0.75rem;
  font-weight: 500;
}

.page-title {
  margin: 0;
  color: var(--foreground);
  font-size: 1.65rem;
  font-weight: 700;
  letter-spacing: -0.04em;
}

.page-subtitle {
  margin: 0.25rem 0 0;
  color: var(--muted-foreground);
  font-size: 0.82rem;
}

/* Pricing Grid */
.pricing-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.85rem;
  margin-top: 0.4rem;
  max-width: 1040px;
  margin-left: auto;
  margin-right: auto;
}

.pricing-card {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 1rem;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  transition: all 0.3s ease;
}

.pricing-card:hover {
  border-color: var(--primary);
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}



.pricing-card.featured {
  border-color: var(--primary);
  background: var(--primary);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.featured .plan-name,
.featured .plan-price,
.featured .plan-price span {
  color: #fff;
}

.plan-name {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin-bottom: 0.25rem;
  text-align: center;
}

.plan-price {
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  margin: 0.55rem 0 0.75rem;
  display: flex;
  align-items: baseline;
  justify-content: flex-start;
  gap: 0.2rem;
}

.plan-price span {
  font-size: 0.75rem;
  font-weight: 500;
  opacity: 0.7;
}

.plan-description {
  font-size: 0.72rem;
  line-height: 1.25;
  margin-bottom: 0.65rem;
  min-height: 1.8rem;
  text-align: center;
}

.featured .plan-description {
    color: rgba(255, 255, 255, 0.8);
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0 0 0.75rem 0;
  flex: 1;
}

.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 0.32rem;
  font-size: 0.68rem;
}

.pricing-card:not(.featured) .plan-features li::before {
  color: #000;
}

.feature-icon {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.45rem 0.7rem;
  border-radius: var(--radius-sm);
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
  cursor: pointer;
  border: 1px solid var(--border);
}

.btn-primary {
  background: var(--primary);
  color: var(--primary-foreground);
  border-color: var(--primary);
}

.featured .btn-primary {
  background: #fff;
  color: #000;
  border-color: #fff;
}

.btn-primary:hover {
  opacity: 0.9;
}

@media (max-width: 1024px) {
  body { overflow: auto; }
  .page-header { left: 0; padding: 0.8rem 1rem 0.7rem; }
  .plans-shell { height: auto; padding-top: 4.75rem; overflow: visible; }
  .pricing-grid { grid-template-columns: 1fr; }
}
</style>

<div class="plans-shell">

  <div class="page-header">
    <div class="page-header-main">
      <h1 class="page-title">Plans & Pricing</h1>
      <p class="page-subtitle">All plans include every product. Higher tiers include more monthly tokens for repeated use.</p>
    </div>
  </div>

  <div class="pricing-grid">
    <?php
    // Always render plans from the database.
    $displayPlans = is_array($plans ?? null) ? $plans : [];

    if (empty($displayPlans)):
    ?>
      <div class="pricing-card" style="grid-column: 1 / -1; text-align: center;">
        <div class="plan-name">No plans available</div>
        <div class="plan-description">
          No active plans were found in the database. Seed or activate plans to display pricing options.
        </div>
      </div>
    <?php
    endif;

    foreach ($displayPlans as $plan):
      $isFeatured = (($plan['slug'] ?? '') === 'professional');
    ?>
      <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">
        <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
        <div class="plan-description"><?= htmlspecialchars($plan['description'] ?? '') ?></div>
        
        <div class="plan-price">
          <span>R</span><?= number_format((float)($plan['price_monthly'] ?? 0), 0) ?>
          <span>/mo</span>
        </div>

        <ul class="plan-features">
          <li><?= number_format((float)($plan['credits_monthly'] ?? 0), 0) ?> tokens / month</li>
          <?php
          $products = is_array($plan['products'] ?? null) ? $plan['products'] : [];
          $features = $products !== []
              ? array_map(static fn(array $product): string => (string)($product['name'] ?? ''), $products)
              : (is_array($plan['features'] ?? null) ? $plan['features'] : []);
          foreach ($features as $feature):
            if ((string)$feature === '') {
                continue;
            }
          ?>
            <li><?= htmlspecialchars($feature) ?></li>
          <?php endforeach; ?>
        </ul>

        <a href="/checkout?plan_id=<?= htmlspecialchars($plan['id']) ?>" class="btn btn-primary w-100">
          <?= $isFeatured ? 'Get Started' : 'Select Plan' ?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

</div>
