<?php $pageTitle = 'Payment Successful'; ?>

<div class="dashboard-shell">
  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Checkout</div>
      <h1 class="page-title">Payment Received</h1>
      <p class="page-subtitle">Your subscription is being activated.</p>
    </div>
  </div>

  <div style="max-width: 480px; margin: 0 auto; text-align: center; padding: 4rem 1rem;">
    <div style="
      width: 56px; height: 56px; border-radius: 50%;
      background: #f0fdf4; border: 1px solid #bbf7d0;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.25rem;
    ">
      <svg width="24" height="24" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    </div>

    <h2 style="font-size: 1.25rem; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.5rem;">
      Payment successful
    </h2>
    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin: 0 0 2rem;">
      <?= htmlspecialchars($message ?? 'Your plan is now active. Credits have been added to your account. It may take a few seconds to reflect.') ?>
    </p>

    <a href="/dashboard" class="btn btn-primary" style="display: inline-flex;">
      Go to Dashboard
    </a>
  </div>
</div>
