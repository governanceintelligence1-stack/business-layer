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

.plans-shell {
  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  padding: 8.25rem 1rem 4rem;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 2rem;
  position: fixed;
  top: 0;
  left: 268px; /* Matches sidebar width */
  right: 0;
  z-index: 60;
  background: white;
  padding: 2rem 2rem 0.75rem;
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
  font-size: 2.25rem;
  font-weight: 700;
  letter-spacing: -0.04em;
}

.page-subtitle {
  margin: 0.5rem 0 0;
  color: var(--muted-foreground);
  font-size: 0.925rem;
}

/* Pricing Grid */
.pricing-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
  margin-top: 1rem;
  max-width: 960px; /* Constrain width to help reduce size */
  margin-left: auto;
  margin-right: auto;
}

.pricing-card {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 1.5rem;
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
  color: var(--primary-foreground);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.featured-badge {
  position: absolute;
  top: -10px;
  left: 50%;
  transform: translateX(-50%);
  background: #000;
  color: #fff;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border: 2px solid #fff;
}

.featured .featured-badge {
    background: #fff;
    color: #000;
}

.plan-name {
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin-bottom: 0.4rem;
}

.plan-price {
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  margin: 1.25rem 0;
  display: flex;
  align-items: baseline;
  gap: 0.2rem;
}

.plan-price span {
  font-size: 0.85rem;
  font-weight: 500;
  opacity: 0.7;
}

.plan-description {
  font-size: 0.8rem;
  line-height: 1.4;
  margin-bottom: 1.5rem;
  min-height: 2.5rem;
}

.featured .plan-description {
    color: rgba(255, 255, 255, 0.8);
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0 0 1.5rem 0;
  flex: 1;
}

.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.6rem;
  font-size: 0.75rem;
}

.feature-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.6rem 0.8rem;
  border-radius: var(--radius-sm);
  font-size: 0.825rem;
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
  .page-header { left: 0; padding: 2rem 1rem 0.75rem; }
  .plans-shell { padding-top: 9rem; }
  .pricing-grid { grid-template-columns: 1fr; }
}
</style>

<div class="plans-shell">

  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Subscriptions</div>
      <h1 class="page-title">Plans & Pricing</h1>
      <p class="page-subtitle">Choose the perfect plan for your organisation's scale.</p>
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
        <?php if ($isFeatured): ?>
          <div class="featured-badge">Popular</div>
        <?php endif; ?>

        <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
        <div class="plan-description"><?= htmlspecialchars($plan['description'] ?? '') ?></div>
        
        <div class="plan-price">
          <span>R</span><?= number_format((float)($plan['price_monthly'] ?? 0), 0) ?>
          <span>/mo</span>
        </div>

        <ul class="plan-features">
          <?php 
          $features = is_array($plan['features']) ? $plan['features'] : [];
          foreach ($features as $feature): 
          ?>
            <li>
              <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
              </svg>
              <?= htmlspecialchars($feature) ?>
            </li>
          <?php endforeach; ?>
        </ul>

        <a href="/checkout?plan_id=<?= htmlspecialchars($plan['id']) ?>" class="btn btn-primary w-100">
          <?= $isFeatured ? 'Get Started' : 'Select Plan' ?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

</div>
