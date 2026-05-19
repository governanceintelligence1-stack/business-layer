<?php $pageTitle = 'Billing & Invoices'; ?>

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
  --destructive: #dc2626;
  --destructive-foreground: #fafafa;
  --success: #16a34a;
  --border: #e4e4e7;
  --radius: 0.75rem;
  --radius-sm: 0.5rem;
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
  --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.05);
  --font-sans: Inter, Roboto, "Segoe UI", sans-serif;
}

.billing-shell {
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
  margin-bottom: 1.5rem;
  position: fixed;
  top: 0;
  left: 276px;
  right: 0;
  max-width: none;
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
  font-size: clamp(1.75rem, 2vw, 2.25rem);
  line-height: 1.12;
  font-weight: 700;
  letter-spacing: -0.04em;
}

.page-subtitle {
  margin: 0.5rem 0 0;
  color: var(--muted-foreground);
  font-size: 0.925rem;
}

.card-grid {
  display: grid;
  gap: 1rem;
  margin-bottom: 1rem;
}

.card-grid-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.card-grid-2 {
  grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.85fr);
}

.card {
  background: var(--card);
  color: var(--card-foreground);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  padding: 1.25rem;
  box-shadow: var(--shadow-sm);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.card-title {
  margin: 0;
  color: var(--foreground);
  font-size: 1rem;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.card-description {
  margin: 0.2rem 0 0;
  color: var(--muted-foreground);
  font-size: 0.82rem;
}

.stat-card {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.stat-label {
  color: var(--muted-foreground);
  font-size: 0.8rem;
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.stat-value {
  color: var(--foreground);
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.25rem;
  padding: 0.5rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 150ms ease;
}

.btn:hover {
  background: var(--accent);
}

.btn-primary {
  background: var(--primary);
  color: var(--primary-foreground);
  border-color: var(--primary);
}

.btn-primary:hover {
  background: #27272a;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-dot {
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 999px;
  background: currentColor;
}

.badge-active { color: var(--success); border-color: rgba(22, 163, 74, 0.2); background: rgba(22, 163, 74, 0.05); }
.badge-pending { color: #ca8a04; border-color: rgba(202, 138, 4, 0.2); background: rgba(202, 138, 4, 0.05); }

.table-wrap {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

th {
  text-align: left;
  padding: 0.75rem 1rem;
  background: var(--muted);
  color: var(--muted-foreground);
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid var(--border);
}

td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border);
  color: var(--foreground);
}

tr:last-child td { border-bottom: 0; }
tr:hover td { background: var(--muted); }

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  border: 1px dashed var(--border);
  border-radius: var(--radius-sm);
}

.empty-state-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.billing-form-grid {
  display: grid;
  gap: 0.75rem;
}

.billing-form-grid.two {
  grid-template-columns: 1fr 1fr;
}

.billing-input {
  width: 100%;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0.6rem 0.7rem;
  font-size: 0.86rem;
  font-family: inherit;
}

.billing-input:focus {
  outline: none;
  border-color: var(--primary);
}

.payment-method-item {
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.add-card-panel {
  margin-top: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 1rem;
  background: #fafafa;
}

@media (max-width: 1024px) {
  .page-header { left: 0; padding: 2rem 1rem 0.75rem; }
  .billing-shell { padding-top: 9rem; }
  .card-grid-3, .card-grid-2 { grid-template-columns: 1fr; }
  .billing-form-grid.two { grid-template-columns: 1fr; }
}
</style>

<div class="billing-shell">

  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Finance</div>
      <h1 class="page-title">Billing & Invoices</h1>
      <p class="page-subtitle">Manage your billing information, payment methods, and view your history.</p>
    </div>
    <div class="page-header-actions">
      <a href="/plans" class="btn btn-primary">Change Plan</a>
    </div>
  </div>

  <?php if (isset($invoice)): ?>
    <!-- Single Invoice View -->
    <div class="card">
      <div class="card-header">
        <div>
          <h3 class="card-title">Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h3>
          <p class="card-description">Issued on <?= htmlspecialchars(substr($invoice['created_at'], 0, 10)) ?></p>
        </div>
        <span class="badge badge-<?= ($invoice['status'] ?? '') === 'paid' ? 'active' : 'pending' ?>">
          <span class="badge-dot"></span>
          <?= htmlspecialchars(ucfirst($invoice['status'] ?? '')) ?>
        </span>
      </div>

      <div class="card-grid card-grid-3 mb-4">
        <div class="stat-card card">
          <span class="stat-label">Amount Due</span>
          <span class="stat-value">R<?= number_format((float)($invoice['amount_total'] ?? $invoice['total'] ?? 0), 2) ?></span>
        </div>
        <div class="stat-card card">
          <span class="stat-label">Due Date</span>
          <span class="stat-value" style="font-size: 1.25rem;"><?= htmlspecialchars($invoice['due_date'] ?? '—') ?></span>
        </div>
        <div class="stat-card card">
          <span class="stat-label">Payment Method</span>
          <span class="stat-value" style="font-size: 1.25rem;">•••• 4242</span>
        </div>
      </div>

      <?php if (!empty($invoice['line_items'])): ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th style="text-align:right;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoice['line_items'] as $li): ?>
                <tr>
                  <td><?= htmlspecialchars($li['description'] ?? '') ?></td>
                  <td><?= htmlspecialchars($li['quantity'] ?? '1') ?></td>
                  <td>R<?= number_format((float)($li['unit_price'] ?? 0), 2) ?></td>
                  <td style="text-align:right; font-weight:600;">R<?= number_format((float)($li['total'] ?? 0), 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <a href="/billing" class="btn btn-secondary">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to Invoices
        </a>
        <button class="btn btn-primary">Download PDF</button>
      </div>
    </div>

  <?php else: ?>
    <!-- Billing Overview -->
    <div class="card-grid card-grid-3" style="margin-bottom: 1.5rem;">
      <div class="stat-card card">
        <span class="stat-label">Next Invoice</span>
        <span class="stat-value"><?= htmlspecialchars($nextInvoiceDate ?? '—') ?></span>
      </div>
      <div class="stat-card card">
        <span class="stat-label">Last Payment</span>
        <span class="stat-value">R<?= number_format((float)($lastPaymentAmount ?? 0), 2) ?></span>
      </div>
      <div class="stat-card card">
        <span class="stat-label">Active Plan</span>
        <span class="stat-value" style="font-size: 1.25rem;"><?= htmlspecialchars($activePlan ?? '—') ?></span>
      </div>
    </div>

    <div class="card-grid card-grid-2">
      <!-- Invoices -->
      <div class="card">
        <div class="card-header">
          <div>
            <h3 class="card-title">Invoices</h3>
            <p class="card-description">Your recent billing history and status.</p>
          </div>
        </div>

        <?php if (!empty($invoices)): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Date</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($invoices as $inv): ?>
                  <tr>
                    <td style="font-family:monospace;"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><?= htmlspecialchars(substr($inv['created_at'], 0, 10)) ?></td>
                    <td style="font-weight:600;">R<?= number_format((float)($inv['amount_total'] ?? $inv['total'] ?? 0), 2) ?></td>
                    <td>
                      <span class="badge badge-<?= ($inv['status'] ?? '') === 'paid' ? 'active' : 'pending' ?>">
                        <span class="badge-dot"></span>
                        <?= htmlspecialchars(ucfirst($inv['status'] ?? '')) ?>
                      </span>
                    </td>
                    <td style="text-align:right;">
                      <a href="/billing/invoice/<?= htmlspecialchars($inv['id']) ?>" class="btn btn-sm btn-ghost">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <div style="margin-top:0.75rem; display:flex; justify-content:flex-end;">
          <a href="/billing/history" class="btn">View all invoices</a>
        </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-state-icon">💳</div>
            <h3>No invoices yet</h3>
            <p>Your invoices will appear here once billing is active.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Payment Methods -->
      <div class="card">
        <div class="card-header">
          <div>
            <h3 class="card-title">Payment Methods</h3>
            <p class="card-description">Manage how you pay for your subscription.</p>
          </div>
        </div>

        <?php $methods = $paymentMethods ?? []; ?>
        <?php if (!empty($methods)): ?>
          <?php foreach ($methods as $pm): ?>
            <div class="payment-method-item">
              <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 40px; height: 25px; background:rgb(185, 185, 185); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; color: #666;"><?= strtoupper(htmlspecialchars((string)($pm['brand'] ?? 'CARD'))) ?></div>
                <div>
                  <div style="font-size: 0.875rem; font-weight: 600;"><?= htmlspecialchars((string)($pm['brand'] ?? 'Card')) ?> ending in <?= htmlspecialchars((string)($pm['last4'] ?? '0000')) ?></div>
                  <div style="font-size: 0.75rem; color: var(--muted-foreground);">Expires <?= htmlspecialchars((string)($pm['expiry_month'] ?? '--')) ?>/<?= htmlspecialchars((string)($pm['expiry_year'] ?? '----')) ?></div>
                </div>
              </div>
              <?php if (!empty($pm['is_default'])): ?>
                <span class="badge">Primary</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state" style="margin-bottom: 1rem;">
            <div class="empty-state-icon">💳</div>
            <h3>No payment methods yet</h3>
            <p>Add your first card below.</p>
          </div>
        <?php endif; ?>

        <div style="margin-top: 1rem; display:flex; justify-content:flex-end;">
          <button class="btn btn-sm btn-primary" type="button" id="showAddCardBtn">Add New Card</button>
        </div>

        <div id="addCardPanel" class="add-card-panel" style="display: none;">
          <form method="post" action="/billing/payment-methods">
            <div class="billing-form-grid">
              <input class="billing-input" type="text" name="cardholder_name" placeholder="Cardholder name" required>
              <input class="billing-input" type="text" name="card_number" inputmode="numeric" placeholder="Card number" required>
            </div>
            <div class="billing-form-grid two" style="margin-top: 0.75rem;">
              <input class="billing-input" type="text" name="expiry_month" inputmode="numeric" placeholder="Expiry month (MM)" required>
              <input class="billing-input" type="text" name="expiry_year" inputmode="numeric" placeholder="Expiry year (YYYY)" required>
            </div>
            <div class="billing-form-grid two" style="margin-top: 0.75rem;">
              <input class="billing-input" type="password" name="cvc" inputmode="numeric" placeholder="CVC (not stored)" autocomplete="off">
              <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.82rem; color:var(--muted-foreground);">
                <input type="checkbox" name="set_default" value="1">
                Set as default payment method
              </label>
            </div>
            <div style="margin-top: 0.9rem; display:flex; justify-content:flex-end; gap:0.5rem;">
              <button class="btn btn-sm btn-secondary" type="button" id="cancelAddCardBtn">Cancel</button>
              <button class="btn btn-primary btn-sm" type="submit">Save Card</button>
            </div>
            <p style="margin-top:0.65rem; margin-bottom:0; font-size:0.75rem; color:var(--muted-foreground);">
              For security, full card number and CVC are never stored. Only masked details (brand, last 4 digits, expiry) are saved.
            </p>
          </form>
        </div>
        <script>
          (function () {
            var showBtn = document.getElementById('showAddCardBtn');
            var cancelBtn = document.getElementById('cancelAddCardBtn');
            var panel = document.getElementById('addCardPanel');
            if (!showBtn || !panel) return;
            showBtn.addEventListener('click', function () {
              panel.style.display = 'block';
              showBtn.style.display = 'none';
            });
            if (cancelBtn) {
              cancelBtn.addEventListener('click', function () {
                panel.style.display = 'none';
                showBtn.style.display = 'inline-flex';
              });
            }
          })();
        </script>

        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--muted); border-radius: var(--radius-sm); font-size: 0.82rem; color: var(--muted-foreground);">
          <strong>Billing Address:</strong><br>
          123 Business Way, Suite 100<br>
          Johannesburg, 2000<br>
          South Africa
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>
