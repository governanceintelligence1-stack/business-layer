<?php $pageTitle = 'Transactions History'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Transactions</h1>
    <p class="page-subtitle">All payment transactions for your organisation.</p>
  </div>
  <a href="/subscriptions" class="btn">Back to Subscriptions</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Transaction History</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Reference</th>
          <th>Invoice</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Provider Tx</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr><td colspan="6" style="text-align:center;padding:1rem;">No transactions found.</td></tr>
        <?php else: ?>
          <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><?= htmlspecialchars(substr($tx['created_at'] ?? '', 0, 19)) ?></td>
              <td style="font-family:monospace;"><?= htmlspecialchars($tx['merchant_reference'] ?? '') ?></td>
              <td><?= htmlspecialchars($tx['invoice_id'] ?? '—') ?></td>
              <td style="font-weight:700;">R<?= number_format((float)($tx['amount'] ?? 0), 2) ?></td>
              <td>
                <span class="badge badge-<?= ($tx['status'] ?? '') === 'successful' ? 'active' : 'pending' ?>">
                  <?= htmlspecialchars(ucfirst($tx['status'] ?? '')) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($tx['provider_transaction_id'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
