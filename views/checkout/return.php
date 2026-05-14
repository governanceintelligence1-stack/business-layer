<?php
$outcome = $outcome ?? 'pending';
$headTitle = match ($outcome) {
    'success' => 'Payment complete',
    'failed' => 'Payment failed',
    'cancelled' => 'Payment cancelled',
    'unknown' => 'Payment reference',
    default => 'Payment pending',
};
$headSubtitle = match ($outcome) {
    'success' => 'Thank you — your subscription is updating.',
    'failed' => 'No successful charge was completed for this attempt.',
    'cancelled' => 'This checkout did not complete.',
    'unknown' => 'We could not match this return to a stored payment.',
    default => 'We are waiting for confirmation from PayFast.',
};
?>

<div class="dashboard-shell">
  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Checkout</div>
      <h1 class="page-title"><?= htmlspecialchars($headTitle) ?></h1>
      <p class="page-subtitle"><?= htmlspecialchars($headSubtitle) ?></p>
    </div>
  </div>

  <div style="max-width: 480px; margin: 0 auto; text-align: center; padding: 4rem 1rem;">
    <?php if ($outcome === 'success'): ?>
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
    <?php elseif ($outcome === 'failed'): ?>
      <div style="
        width: 56px; height: 56px; border-radius: 50%;
        background: #fef2f2; border: 1px solid #fecaca;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.25rem;
      ">
        <svg width="24" height="24" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </div>
      <h2 style="font-size: 1.25rem; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.5rem;">
        Payment failed
      </h2>
    <?php elseif ($outcome === 'cancelled'): ?>
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
    <?php elseif ($outcome === 'unknown'): ?>
      <div style="
        width: 56px; height: 56px; border-radius: 50%;
        background: #fffbeb; border: 1px solid #fde68a;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.25rem;
      ">
        <svg width="24" height="24" fill="none" stroke="#ca8a04" stroke-width="2.5" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      <h2 style="font-size: 1.25rem; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.5rem;">
        Could not verify payment
      </h2>
    <?php else: ?>
      <div style="
        width: 56px; height: 56px; border-radius: 50%;
        background: #fffbeb; border: 1px solid #fde68a;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.25rem;
      ">
        <svg width="24" height="24" fill="none" stroke="#ca8a04" stroke-width="2.5" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      </div>
      <h2 style="font-size: 1.25rem; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.5rem;">
        Payment pending
      </h2>
    <?php endif; ?>

    <p style="color: var(--muted-foreground); font-size: 0.875rem; margin: 0 0 2rem;">
      <?= htmlspecialchars($message ?? '') ?>
    </p>

    <?php if ($outcome === 'success'): ?>
      <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
        <a href="/billing" class="btn btn-secondary">Billing</a>
      </div>
    <?php elseif ($outcome === 'failed' || $outcome === 'cancelled'): ?>
      <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <a href="/plans" class="btn btn-primary">View Plans</a>
        <a href="/dashboard" class="btn btn-secondary">Back to Dashboard</a>
      </div>
    <?php elseif ($outcome === 'unknown'): ?>
      <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <a href="/dashboard" class="btn btn-primary">Back to Dashboard</a>
        <a href="/billing" class="btn btn-secondary">Billing</a>
      </div>
    <?php else: ?>
      <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
        <a href="/billing" class="btn btn-primary">View Billing</a>
        <a href="/dashboard" class="btn btn-secondary">Dashboard</a>
      </div>
    <?php endif; ?>
  </div>
</div>
