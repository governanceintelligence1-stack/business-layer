<?php $pageTitle = 'Billing'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Billing</h1>
    <p class="page-subtitle">View invoices and payment history for your organisation.</p>
  </div>
</div>

<?php if (isset($invoice)): ?>
<!-- Single Invoice View -->
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h3>
    <span class="badge badge-<?= ($invoice['status'] ?? '') === 'paid' ? 'active' : 'pending' ?>">
      <?= htmlspecialchars(ucfirst($invoice['status'] ?? '')) ?>
    </span>
  </div>
  <div class="form-row form-row-3 mb-3">
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Invoice Date</div>
      <div><?= htmlspecialchars(substr($invoice['created_at'], 0, 10)) ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Due Date</div>
      <div><?= htmlspecialchars($invoice['due_date'] ?? '—') ?></div>
    </div>
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);">Total</div>
      <div style="font-size:1.2rem;font-weight:700;color:var(--accent);">
        R<?= number_format((float)($invoice['amount_total'] ?? 0), 2) ?>
      </div>
    </div>
  </div>

  <?php if (!empty($invoice['line_items'])): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Description</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoice['line_items'] as $li): ?>
        <tr>
          <td><?= htmlspecialchars($li['description'] ?? '') ?></td>
          <td><?= htmlspecialchars($li['quantity'] ?? '1') ?></td>
          <td>R<?= number_format((float)($li['unit_price'] ?? 0), 2) ?></td>
          <td>R<?= number_format((float)($li['total'] ?? 0), 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div style="text-align:right;margin-top:1rem;">
    <a href="/billing" class="btn btn-ghost btn-sm">← Back to Invoices</a>
  </div>
</div>
<?php else: ?>
<!-- Invoice List -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Invoices</h3>
  </div>
  <?php if (!empty($invoices)): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Date</th>
          <th>Due Date</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
        <tr>
          <td style="font-family:monospace;color:var(--accent);"><?= htmlspecialchars($inv['invoice_number']) ?></td>
          <td style="font-size:.85rem;color:var(--text-muted);"><?= htmlspecialchars(substr($inv['created_at'], 0, 10)) ?></td>
          <td style="font-size:.85rem;color:var(--text-muted);"><?= htmlspecialchars($inv['due_date'] ?? '—') ?></td>
          <td style="font-weight:600;">R<?= number_format((float)($inv['amount_total'] ?? 0), 2) ?></td>
          <td>
            <span class="badge badge-<?= ($inv['status'] ?? '') === 'paid' ? 'active' : 'pending' ?>">
              <?= htmlspecialchars(ucfirst($inv['status'] ?? '')) ?>
            </span>
          </td>
          <td>
            <a href="/billing/invoice/<?= htmlspecialchars($inv['id']) ?>" class="btn btn-ghost btn-sm">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon">💳</div>
    <h3>No invoices yet</h3>
    <p>Your invoices will appear here once billing is active.</p>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
