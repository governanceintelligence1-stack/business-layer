<?php $pageTitle = 'Payment Cancelled'; ?>

<div class="dashboard-shell">
  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Checkout</div>
      <h1 class="page-title">Payment Cancelled</h1>
      <p class="page-subtitle">No charge was made to your account.</p>
    </div>
  </div>

  <div style="max-width: 480px; margin: 0 auto; text-align: center; padding: 4rem 1rem;">
    <div style="
      width: 56px; height: 56px; border-radius: 50%;
      background: #fafafa; border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.25rem;
    ">
      <svg width="24" height="24" fill="none" stroke="#71717a" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </div>

    <h2 style="font-size: 1.25rem; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.5rem;">
      Payment cancelled
    </h2>
    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin: 0 0 2rem;">
      You cancelled the checkout. Your plan has not changed and no payment was processed.
    </p>

    <div style="display: flex; gap: 0.75rem; justify-content: center;">
      <a href="/plans" class="btn btn-primary">View Plans</a>
      <a href="/dashboard" class="btn btn-secondary">Back to Dashboard</a>
    </div>
  </div>
</div>
