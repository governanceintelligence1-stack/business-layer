<?php $pageTitle = 'Checkout'; ?>
<?php
$planName = (string)($plan['name'] ?? 'Selected Plan');
$monthly = (float)($plan['price_monthly'] ?? 0);
$annual = (float)($plan['price_annual'] ?? ($monthly * 12));
$credits = (int)($plan['credits_monthly'] ?? 0);
$features = $plan['features'] ?? [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];

if (is_string($features)) {
    $decoded = json_decode($features, true);
    $features = is_array($decoded) ? $decoded : [];
}

if (!is_array($features)) {
    $features = [];
}

$isAssoc = array_keys($features) !== range(0, count($features) - 1);
if ($isAssoc) {
    $featureLabelMap = [
        'ocr' => 'OCR',
        'api_access' => 'API Access',
        'mxa_mobile' => 'MXA Mobile',
        'transcription' => 'Transcription',
        'forensic_upload' => 'Forensic Upload',
        'bank_statement_analysis' => 'Bank Statement Analysis',
        'custom_limits' => 'Custom Limits',
        'file_comparison' => 'File Comparison',
        'priority_support' => 'Priority Support',
    ];

    $normalized = [];
    foreach ($features as $key => $enabled) {
        if (!$enabled) {
            continue;
        }

        $normalized[] = $featureLabelMap[$key] ?? ucwords(str_replace('_', ' ', (string)$key));
    }

    $features = $normalized;
}

$planSlug = strtolower((string)($plan['slug'] ?? ''));
$isFeatured = in_array($planSlug, ['professional', 'business-pro', 'pro'], true);

$defaultMethodId = '';
foreach ($paymentMethods as $pm) {
    if (!empty($pm['is_default'])) {
        $defaultMethodId = (string)$pm['id'];
        break;
    }
}

if ($defaultMethodId === '' && !empty($paymentMethods[0]['id'])) {
    $defaultMethodId = (string)$paymentMethods[0]['id'];
}
?>

<div class="dashboard-shell">
  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Checkout</div>
      <h1 class="page-title">Confirm Your Subscription</h1>
      <p class="page-subtitle">Review your selected plan, order details, and payment method.</p>
    </div>
  </div>

  <form method="post" action="/checkout/pay" class="checkout-layout">
    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string)($checkoutIdempotencyKey ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="plan_id" value="<?= htmlspecialchars((string)($plan['id'] ?? '')) ?>">
    <input type="hidden" name="plan_name" value="<?= htmlspecialchars($planName) ?>">

    <!-- Card 1: Chosen Subscription -->
    <section class="checkout-selected-card">
      <div class="checkout-section-title">Chosen Subscription</div>

      <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">
        <div class="plan-name"><?= htmlspecialchars($planName) ?></div>

        <div class="plan-desc">
          <?= htmlspecialchars((string)($plan['description'] ?? '')) ?>
        </div>

        <div class="checkout-price-inline">
          <strong>R<?= number_format($monthly, 0) ?></strong>
          per month · <?= number_format($credits) ?> tokens included
        </div>

        <?php if (!empty($features)): ?>
          <ul class="plan-features">
            <?php foreach (array_slice($features, 0, 8) as $feature): ?>
              <li><?= htmlspecialchars(is_array($feature) ? ($feature['label'] ?? '') : (string)$feature) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <div class="checkout-plan-note">
          This is the subscription you selected from the Plans page. You can change it before payment.
        </div>

        <a href="/plans" class="btn btn-primary checkout-change-plan-btn">Change Plan</a>
      </div>
    </section>

    <!-- Card 2: Order Details and Payment -->
    <section class="checkout-panel">
      <div class="checkout-section-title">Order Details</div>

      <div class="billing-cycle-grid">
        <label class="billing-cycle-option">
          <input type="radio" name="billing_cycle" value="monthly" checked>
          <span>
            <span class="billing-title">Monthly</span>
            <span class="billing-meta">R<?= number_format($monthly, 2) ?> billed monthly</span>
          </span>
        </label>

        <label class="billing-cycle-option">
          <input type="radio" name="billing_cycle" value="annual">
          <span>
            <span class="billing-title">Annual</span>
            <span class="billing-meta">R<?= number_format($annual, 2) ?> billed yearly</span>
          </span>
        </label>
      </div>

      <div class="order-summary-box">
        <div class="order-row">
          <span>Subscription</span>
          <strong><?= htmlspecialchars($planName) ?></strong>
        </div>

        <div class="order-row">
          <span>Monthly tokens</span>
          <span id="monthly-tokens-value"><?= number_format($credits) ?></span>
        </div>

        <div class="order-row">
          <span>Billing cycle</span>
          <span id="billing-cycle-label">Monthly</span>
        </div>

        <div class="order-row order-row-annual-tokens" id="annual-tokens-row" style="display: none;">
          <span>Total tokens</span>
          <strong id="annual-tokens-total">0</strong>
        </div>

        <div class="order-row total">
          <span>Total due now</span>
          <span id="order-total">R<?= number_format($monthly, 2) ?></span>
        </div>
      </div>

      <div class="payment-divider"></div>

      <div class="checkout-section-title">Payment Method</div>

      <?php if (!empty($paymentMethods)): ?>
        <?php foreach ($paymentMethods as $pm): ?>
          <?php
          $pmId = (string)($pm['id'] ?? '');
          $isSelected = $pmId !== '' && $pmId === $defaultMethodId;
          $expiry = trim((string)($pm['expiry_month'] ?? '') . '/' . (string)($pm['expiry_year'] ?? ''));
          ?>
          <label class="saved-method">
            <input
              type="radio"
              name="payment_method_choice"
              value="<?= htmlspecialchars($pmId) ?>"
              <?= $isSelected ? 'checked' : '' ?>
            >
            <div>
              <div>
                <strong><?= htmlspecialchars((string)($pm['brand'] ?? 'Card')) ?></strong>
                ending in <?= htmlspecialchars((string)($pm['last4'] ?? '0000')) ?>
              </div>
              <div class="saved-method-meta">
                Expires <?= htmlspecialchars($expiry !== '/' ? $expiry : '--/----') ?>
                <?= !empty($pm['is_default']) ? ' · Default card' : '' ?>
              </div>
            </div>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>

      <label class="saved-method">
        <input
          type="radio"
          name="payment_method_choice"
          value="new"
          <?= empty($paymentMethods) ? 'checked' : '' ?>
        >
        <div>
          <div><strong>Use a new card</strong></div>
          <div class="saved-method-meta">Enter card details for this payment</div>
        </div>
      </label>

      <div id="new-card-fields" class="new-card-fields">
        <div class="new-card-grid">
          <input class="form-input" type="text" name="cardholder_name" placeholder="Cardholder name">
          <input class="form-input" type="text" name="card_number" placeholder="Card number">
        </div>

        <div class="new-card-grid two">
          <input class="form-input" type="text" name="expiry_month" placeholder="Expiry month (MM)">
          <input class="form-input" type="text" name="expiry_year" placeholder="Expiry year (YYYY)">
        </div>

        <div class="new-card-grid two">
          <input class="form-input" type="text" name="cvc" placeholder="CVC">
          <label class="small-muted" style="display:flex; align-items:center; gap:0.45rem;">
            <input type="checkbox" name="save_card" value="1">
            Save this card for future payments
          </label>
        </div>
      </div>

      <div class="checkout-actions">
        <a href="/plans" class="btn btn-secondary">Back to Plans</a>
        <button type="submit" class="btn btn-primary">Continue to PayFast</button>
      </div>
    </section>
  </form>
</div>

<script>
(function () {
  const monthlyRadio = document.querySelector('input[name="billing_cycle"][value="monthly"]');
  const annualRadio = document.querySelector('input[name="billing_cycle"][value="annual"]');
  const totalEl = document.getElementById('order-total');
  const billingCycleLabel = document.getElementById('billing-cycle-label');

  const monthlyAmount = <?= json_encode(number_format($monthly, 2, '.', '')) ?>;
  const annualAmount = <?= json_encode(number_format($annual, 2, '.', '')) ?>;
  const monthlyTokensEl = document.getElementById('monthly-tokens-value');
  const annualTokensRow = document.getElementById('annual-tokens-row');
  const annualTokensTotal = document.getElementById('annual-tokens-total');
  function parseMonthlyTokens() {
    if (!monthlyTokensEl) {
      return 0;
    }
    const raw = monthlyTokensEl.textContent.replace(/,/g, '').trim();
    const parsed = parseInt(raw, 10);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  const paymentRadios = document.querySelectorAll('input[name="payment_method_choice"]');
  const newCardFields = document.getElementById('new-card-fields');

  function updateTotal() {
    const isAnnual = annualRadio && annualRadio.checked;
    const value = isAnnual ? annualAmount : monthlyAmount;

    if (totalEl) {
      totalEl.textContent = 'R' + value;
    }

    if (billingCycleLabel) {
      billingCycleLabel.textContent = isAnnual ? 'Annual' : 'Monthly';
    }

    if (annualTokensRow) {
      annualTokensRow.style.display = isAnnual ? 'flex' : 'none';
    }

    if (isAnnual && annualTokensTotal) {
      const monthly = parseMonthlyTokens();
      annualTokensTotal.textContent = (monthly * 12).toLocaleString('en-ZA');
    }
  }

  function updateNewCardVisibility() {
    const selected = document.querySelector('input[name="payment_method_choice"]:checked');
    const showNew = !selected || selected.value === 'new';

    if (newCardFields) {
      newCardFields.style.display = showNew ? 'block' : 'none';
    }
  }

  if (monthlyRadio) {
    monthlyRadio.addEventListener('change', updateTotal);
  }

  if (annualRadio) {
    annualRadio.addEventListener('change', updateTotal);
  }

  paymentRadios.forEach(function (radio) {
    radio.addEventListener('change', updateNewCardVisibility);
  });

  updateTotal();
  updateNewCardVisibility();
})();
</script>