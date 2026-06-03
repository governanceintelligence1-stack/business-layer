<?php $pageTitle = 'Subscription History'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Subscription History</h1>
    <p class="page-subtitle">All subscriptions for your organisation.</p>
  </div>
  <a href="/subscriptions" class="btn">Back to Subscriptions</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">All Subscriptions</h3>
  </div>
  <?php require __DIR__ . '/_history-table.php'; ?>
</div>
