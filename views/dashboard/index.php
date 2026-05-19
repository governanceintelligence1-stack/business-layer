<?php $pageTitle = 'Dashboard'; ?>

<style>
/* ============================================================
   shadcn / Flux UI Inspired Dashboard Theme
   Black / White / Neutral UI
   ============================================================ */

:root {
  --background: #ffffff;
  --foreground: #09090b;

  --card: #ffffff;
  --card-foreground: #09090b;

  --popover: #ffffff;
  --popover-foreground: #09090b;

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
  --input: #e4e4e7;
  --ring: #18181b;

  --radius: 0.75rem;
  --radius-sm: 0.5rem;
  --radius-lg: 1rem;

  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
  --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.05);

  --font-sans: Inter, Roboto, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* ============================================================
   Base
   ============================================================ */

* {
  box-sizing: border-box;
}

html,
body {
  margin: 0;
  background: var(--background);
  color: var(--foreground);
  font-family: var(--font-sans);
  font-size: 16px;
  line-height: 1.5;
}

body {
  min-height: 100vh;
}

a {
  color: inherit;
}

/* Remove old gold/accent styling */
[style*="gold"],
[style*="#d4af37"],
[style*="#D4AF37"],
[style*="#c9a227"],
[style*="#C9A227"],
[style*="#b8860b"],
[style*="#B8860B"] {
  border-color: var(--border) !important;
  color: var(--foreground) !important;
  background: transparent !important;
}

/* ============================================================
   App Layout / Sidebar Compatibility
   ============================================================ */

.app-shell,
.layout,
.dashboard-layout {
  display: flex;
  min-height: 100vh;
  background: var(--background);
  color: var(--foreground);
}

.main-content,
.content,
main {
  flex: 1;
  min-width: 0;
  background: var(--background);
  color: var(--foreground);
}

/* ============================================================
   Dashboard Shell
   ============================================================ */

.dashboard-shell {
  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  padding: 8.25rem 1rem 4rem;
}

/* ============================================================
   Page Header
   ============================================================ */

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

.page-header-main {
  min-width: 0;
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
.page-header-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
}
.page-header-meta {
  display: inline-flex;
  align-items: baseline;
  gap: 0.3rem;
  font-size: 0.9rem;
  color: var(--muted-foreground);
  white-space: nowrap;
}
.page-header-meta strong {
  color: var(--foreground);
  font-size: 0.9rem;
  font-weight: 700;
}

/* ============================================================
   Grid
   ============================================================ */

.card-grid {
  display: grid;
  gap: 1rem;
}

.card-grid-4 {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.card-grid-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.bento-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, 1fr);
  grid-auto-rows: minmax(132px, auto);
}

.span-2 { grid-column: span 2; }
.span-3 { grid-column: span 3; }
.span-row-2 { grid-row: span 2; }

.mb-4 {
  margin-bottom: 1rem;
}

/* ============================================================
   Cards
   ============================================================ */

.card,
.stat-card {
  background: var(--card);
  color: var(--card-foreground);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
}

.card {
  padding: 1rem;
}

.stat-card {
  min-height: 132px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: box-shadow 150ms ease, transform 150ms ease;
}

.stat-card::before {
  content: none !important;
  display: none !important;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.stat-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border: 1px solid #bbf7d0;
  border-radius: var(--radius-sm);
  background: #f0fdf4;
  color: #15803d;
  font-size: 0.85rem;
  font-weight: 650;
}

.stat-label {
  color: var(--muted-foreground);
  font-size: 0.78rem;
  font-weight: 500;
}

.stat-value {
  margin-top: 0.45rem;
  color: var(--foreground);
  font-size: 1.85rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: -0.04em;
}

.stat-value-sm {
  font-size: 1.25rem;
  letter-spacing: -0.025em;
}

.stat-sub {
  margin-top: 0.75rem;
  color: var(--muted-foreground);
  font-size: 0.82rem;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.card-title {
  margin: 0;
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.card-description {
  margin: 0.2rem 0 0;
  color: var(--muted-foreground);
  font-size: 0.82rem;
}

/* ============================================================
   Buttons
   ============================================================ */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.35rem;
  padding: 0.55rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--background);
  color: var(--foreground);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1;
  white-space: nowrap;
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  transition:
    background-color 150ms ease,
    color 150ms ease,
    border-color 150ms ease,
    box-shadow 150ms ease,
    transform 150ms ease;
}

.btn:hover {
  background: var(--accent);
  color: var(--accent-foreground);
}

.btn:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(24, 24, 27, 0.12);
}

.btn-primary {
  background: var(--primary);
  color: var(--primary-foreground);
  border-color: var(--primary);
}

.btn-primary:hover {
  background: #27272a;
  border-color: #27272a;
  color: var(--primary-foreground);
}

.btn-secondary {
  background: var(--secondary);
  color: var(--secondary-foreground);
  border-color: var(--border);
}

.btn-secondary:hover {
  background: #e4e4e7;
}

.btn-ghost {
  background: transparent;
  box-shadow: none;
}

.btn-ghost:hover {
  background: var(--accent);
}

.btn-sm {
  min-height: 2rem;
  padding: 0.45rem 0.7rem;
  font-size: 0.8rem;
}

/* ============================================================
   Quick Actions
   ============================================================ */

.action-list {
  display: grid;
  gap: 0.01rem;
}

.action-item {
  width: 100%;
  min-height: 2rem;
  justify-content: space-between;
  padding: 0.1rem 0.1rem;
  border-radius: var(--radius-sm);
  border: none !important;
  background: transparent !important;
  box-shadow: none !important;
}

.action-left {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.action-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.2rem;
  height: 1.2rem;
  border: none;
  border-radius: 0;
  background: transparent;
  color: #15803d;
  font-size: 0.8rem;
  font-weight: 650;
}

.action-text {
  display: grid;
  gap: 0.1rem;
  text-align: left;
}

.action-title {
  color: var(--foreground);
  font-size: 0.875rem;
  font-weight: 550;
  line-height: 1.1;
}

.action-subtitle {
  color: var(--muted-foreground);
  font-size: 0.75rem;
}

.action-arrow {
  color: #16a34a;
  font-size: 1rem;
}

/* ============================================================
   Subscription Details
   ============================================================ */

.subscription-details {
  display: grid;
  gap: 0;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 0.9rem;
  background: var(--background);
  border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
  border-bottom: 0;
}

.detail-label {
  color: var(--muted-foreground);
  font-size: 0.8rem;
}

.detail-value {
  color: var(--foreground);
  font-size: 0.875rem;
  font-weight: 600;
  text-align: right;
}

.detail-value-strong {
  font-weight: 700;
}

/* ============================================================
   Badges
   ============================================================ */

.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  width: fit-content;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.2rem 0.55rem;
  background: var(--background);
  color: var(--foreground);
  font-size: 0.72rem;
  font-weight: 500;
  line-height: 1.2;
  text-transform: capitalize;
}

.badge-dot {
  width: 0.38rem;
  height: 0.38rem;
  border-radius: 999px;
  background: currentColor;
}

.badge-active {
  color: var(--success);
  background: #ffffff;
}

.badge-revoked {
  color: var(--destructive);
  background: #ffffff;
}

/* ============================================================
   Tables
   ============================================================ */

.table-wrap {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
}

table {
  width: 100%;
  border-collapse: collapse;
  background: var(--background);
}

thead {
  background: var(--muted);
}

th {
  padding: 0.75rem 0.85rem;
  text-align: left;
  color: var(--muted-foreground);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.025em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

td {
  padding: 0.85rem;
  color: var(--foreground);
  font-size: 0.86rem;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
  white-space: nowrap;
}

tbody tr:last-child td {
  border-bottom: 0;
}

tbody tr:hover {
  background: var(--muted);
}

.table-date {
  color: var(--muted-foreground);
  font-size: 0.8rem;
}

.amount-credit {
  color: var(--success);
  font-weight: 650;
}

.amount-debit {
  color: var(--destructive);
  font-weight: 650;
}

.balance-after {
  color: var(--foreground);
  font-weight: 650;
}

/* ============================================================
   Empty State
   ============================================================ */

.empty-state {
  display: grid;
  place-items: center;
  text-align: center;
  padding: 2.25rem 1rem;
  border: 1px dashed var(--border);
  border-radius: var(--radius-sm);
  background: var(--muted);
}

.empty-state-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  margin-bottom: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--background);
  color: var(--foreground);
  font-size: 0.85rem;
  font-weight: 650;
}

.empty-state h3 {
  margin: 0;
  color: var(--foreground);
  font-size: 0.95rem;
  font-weight: 650;
}

.empty-state p {
  max-width: 30rem;
  margin: 0.4rem auto 0;
  color: var(--muted-foreground);
  font-size: 0.86rem;
}

/* ============================================================
   Responsive
   ============================================================ */

@media (max-width: 1080px) {
  .card-grid-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .app-shell,
  .layout,
  .dashboard-layout {
    flex-direction: column;
  }

  .card-grid-2 {
    grid-template-columns: 1fr;
  }

  .dashboard-shell {
    padding-top: 8.5rem;
  }

  .page-header {
    left: 0;
    right: 0;
    max-width: none;
  }
}

@media (max-width: 640px) {
  .dashboard-shell {
    padding: 9.5rem 0.85rem 3rem;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .page-header .btn {
    width: 100%;
  }

  .card-grid-4,
  .bento-grid {
    grid-template-columns: 1fr;
  }

  .span-2, .span-3, .span-row-2 {
    grid-column: span 1;
    grid-row: span 1;
  }

  .detail-row {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.25rem;
  }

  .detail-value {
    text-align: left;
  }
}
</style>

<div class="dashboard-shell">

  <div class="page-header">
    <div class="page-header-main">
      <div class="page-eyebrow">Dashboard</div>

      <h1 class="page-title">
        Welcome back, <?= htmlspecialchars(($user['first_name'] ?? 'there')) ?>
      </h1>

      <p class="page-subtitle">
        Here is an overview of your organisation's usage.
      </p>
      
    </div>

    <div class="page-header-actions">
      <div class="page-header-meta">
        Credit Balance: <strong><?= number_format((float) $creditBalance, 0) ?></strong>
      </div>
      <div class="page-header-meta">
        <strong><?= htmlspecialchars($activePlan['plan_name'] ?? '—') ?></strong>
      </div>
      <a href="/plans" class="btn btn-primary">
        Upgrade Plan
      </a>
    </div>
  </div>

  <!-- Bento Grid -->
  <div class="bento-grid">

    <!-- Credit Usage Trend -->
    <?php require __DIR__ . '/components/credit-usage-trend.php'; ?>

    <!-- Updates -->
    <div class="card span-row-2" style="display: flex; flex-direction: column; height: 75%;">
      <div class="card-header" style="margin-bottom: 0.65rem;">
        <h3 class="card-title" style="font-size: 0.95rem;">Updates</h3>
      </div>
      <div class="action-list">
        <?php
        $recentArticles = is_array($recentArticles ?? null) ? $recentArticles : [];
        foreach ($recentArticles as $article):
          $aid = htmlspecialchars((string)($article['id'] ?? ''));
          $title = htmlspecialchars((string)($article['title'] ?? ''));
        ?>
        <a class="btn btn-ghost action-item" href="/updates#<?= $aid ?>">
          <span class="action-left">
            <span class="action-text">
              <span class="action-title"><?= $title ?></span>
            </span>
          </span>
        </a>
        <?php endforeach; ?>
        <?php if (empty($recentArticles)): ?>
        <p style="font-size: 0.8rem; color: var(--muted-foreground); margin: 0;">No published updates yet.</p>
        <?php endif; ?>
      </div>
      <div style="margin-top: auto; padding-top: 0.9rem;">
        <a href="/updates" style="font-size: 0.75rem; color: var(--primary); font-weight: 600; text-decoration: none; display: block; text-align: right;">
          View More →
        </a>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card" style="padding: 1rem; display: flex; flex-direction: column;">
      <div class="card-header" style="margin-bottom: 0.5rem;">
        <h3 class="card-title" style="font-size: 0.85rem;">Recent Credit Transactions</h3>
      </div>

      <?php
      $transactionRows = !empty($transactions)
        ? array_slice($transactions, 0, 3)
        : [
            ['created_at' => '2026-05-06 10:30:00', 'type' => 'debit', 'amount' => 1200, 'product_name' => 'Upload Forensic Image', 'tokens_used' => 1200],
            ['created_at' => '2026-05-05 14:12:00', 'type' => 'debit', 'amount' => 480, 'product_name' => 'OCR', 'tokens_used' => 480],
            ['created_at' => '2026-05-04 09:00:00', 'type' => 'debit', 'amount' => 750, 'product_name' => 'Mobile Forensics', 'tokens_used' => 750],
          ];
      ?>
      <?php if (!empty($transactionRows)): ?>
        <div class="table-wrap">
          <table style="font-size: 0.75rem;">
            <tbody>
              <?php foreach ($transactionRows as $tx): ?>
                <?php
                  $productName = $tx['product_name'] ?? '';
                  if ($productName === '') {
                      $desc = strtolower((string)($tx['description'] ?? ''));
                      if (str_contains($desc, 'ocr')) {
                          $productName = 'OCR';
                      } elseif (str_contains($desc, 'forensic')) {
                          $productName = 'Upload Forensic Image';
                      } elseif (str_contains($desc, 'mobile')) {
                          $productName = 'Mobile Forensics';
                        } elseif (str_contains($desc, 'bank')) {
                          $productName = 'Banking Intelligence';
                      } elseif (str_contains($desc, 'transcription')) {
                          $productName = 'Transcription';
                      } elseif (!empty($tx['ref_type'])) {
                          $productName = ucwords(str_replace(['_', '-'], ' ', (string)$tx['ref_type']));
                      } else {
                          $productName = 'Platform Usage';
                      }
                  }
                  $tokensUsed = (int) round((float)($tx['tokens_used'] ?? $tx['amount'] ?? 0));
                  if ($tokensUsed < 0) {
                      $tokensUsed = abs($tokensUsed);
                  }
                ?>
                <tr>
                  <td style="padding: 0.4rem;"><?= htmlspecialchars(date('j M', strtotime((string) ($tx['created_at'] ?? '')))) ?></td>
                  <td style="padding: 0.4rem; font-weight: 600; color: var(--foreground);">
                    <?= htmlspecialchars($productName) ?>
                  </td>
                  <td style="padding: 0.4rem; text-align: right;">
                    <span class="badge badge-active" style="font-size: 0.65rem; padding: 0.1rem 0.4rem;">
                      <?= number_format($tokensUsed, 0) ?> tokens
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div style="margin-top: auto; padding-top: 0.9rem;">
        <a href="/credits/history" style="font-size: 0.7rem; color: var(--primary); font-weight: 600; text-decoration: none; display: block; text-align: right;">
          View All →
        </a>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="display: flex; flex-direction: column;">
      <div class="card-header" style="margin-bottom: 0.65rem;">
        <h3 class="card-title" style="font-size: 0.95rem;">Quick Actions</h3>
      </div>
      <div class="action-list">
        <a href="/credits" class="btn btn-ghost action-item">
          <span class="action-left">
            <span class="action-text">
              <span class="action-title">Credit Usage</span>
            </span>
          </span>
          <span class="action-arrow">↗</span>
        </a>
        <a href="http://upload.gismartanalytics.com" class="btn btn-ghost action-item">
          <span class="action-left">
            <span class="action-text">
              <span class="action-title">Upload Forensic Image</span>
            </span>
          </span>
          <span class="action-arrow">↗</span>
        </a>
        <a href="http://ocr.gismartanalytics.com" class="btn btn-ghost action-item">
          <span class="action-left">
            <span class="action-text">
              <span class="action-title">OCR</span>
            </span>
          </span>
          <span class="action-arrow">↗</span>
        </a>
        <a href="http://mobile.gismartanalytics.com" class="btn btn-ghost action-item">
          <span class="action-left">
            <span class="action-text">
              <span class="action-title">MOBILE</span>
            </span>
          </span>
          <span class="action-arrow">↗</span>
        </a>
      </div>
    </div>

    <!-- Quick Billing Info -->
    <div class="card" style="padding: 1rem; display: flex; flex-direction: column; height: 70%;">
      <div class="card-header" style="margin-bottom: 0.5rem;">
        <h3 class="card-title">Billing</h3>
        <a href="/billing" class="stat-icon" style="text-decoration: none;"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></a>
      </div>
      <div style="font-size: 0.85rem; color: var(--muted-foreground);">
        Next invoice: <span style="color: var(--foreground); font-weight: 600;">June 1st</span>
      </div>
      <div style="margin-top: auto; padding-top: 0.9rem;">
        <a href="/billing" style="font-size: 0.75rem; color: var(--primary); font-weight: 600; text-decoration: none; display: block; text-align: right;">Invoices →</a>
      </div>
    </div>

  </div>

</div>