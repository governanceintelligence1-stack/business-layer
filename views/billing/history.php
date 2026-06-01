<?php $pageTitle = 'Billing History'; ?>

<div style="max-width:900px;margin:4rem auto;padding:1rem;">
  <h1>Billing History</h1>
  <p>All past invoices. Use the search or pagination to find older records.</p>

  <?php if (empty($invoices)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📄</div>
      <h3>No invoices found</h3>
      <p>There are no invoices for your organisation yet.</p>
    </div>
  <?php else: ?>
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
                <a href="/billing/invoice/<?= htmlspecialchars($inv['id']) ?>" class="btn">View</a>
                <a href="/billing/invoice/<?= htmlspecialchars($inv['id']) ?>/download" class="btn">Download</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:1rem; display:flex; justify-content:center; gap:0.5rem;">
      <?php for ($p = 1; $p <= ($totalPages ?? 1); $p++): ?>
        <a href="/billing/history?page=<?= $p ?>" class="btn <?= ($p === ($page ?? 1)) ? 'btn-primary' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:2rem;">
    <a href="/billing" class="btn">Back to Billing Overview</a>
  </div>
</div>
